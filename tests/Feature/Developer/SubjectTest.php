<?php

namespace Tests\Feature\Developer;

use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $developer;
    protected User $otherDeveloper;
    protected User $reviewer;
    protected Subject $assignedSubject;
    protected Subject $unassignedSubject;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'pengembang']);
        Role::create(['name' => 'reviewer']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->developer = User::factory()->create(['name' => 'Pengembang Utama']);
        $this->developer->assignRole('pengembang');

        $this->otherDeveloper = User::factory()->create(['name' => 'Pengembang Lain']);
        $this->otherDeveloper->assignRole('pengembang');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer 1']);
        $this->reviewer->assignRole('reviewer');

        $training = Training::create([
            'code' => 'TR-01',
            'title' => 'Pelatihan APIPDA',
            'created_by' => $admin->id,
        ]);

        $this->assignedSubject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Overview DAK Fisik',
            'sort_order' => 1,
        ]);

        $this->unassignedSubject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Kebijakan Pengadaan',
            'sort_order' => 2,
        ]);

        // Assign developer to assignedSubject
        $this->assignedSubject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $admin->id,
        ]);

        // Assign reviewer to assignedSubject
        $this->assignedSubject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_developer_can_view_assigned_subjects_list()
    {
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 1)
            ->where('subjects.data.0.id', $this->assignedSubject->id)
        );
    }

    public function test_developer_can_view_assigned_subject_detail()
    {
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.show', $this->assignedSubject));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/show')
            ->where('subject.id', $this->assignedSubject->id)
        );
    }

    public function test_developer_cannot_view_unassigned_subject_detail()
    {
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.show', $this->unassignedSubject));

        $response->assertForbidden();
    }

    public function test_developer_can_create_material_in_assigned_subject()
    {
        $response = $this->actingAs($this->developer)->post(route('developer.subjects.materials.store', $this->assignedSubject), [
            'type' => 'pdf',
            'order' => 1,
        ]);

        $response->assertRedirect(route('developer.subjects.show', $this->assignedSubject));
        $this->assertDatabaseHas('materials', [
            'subject_id' => $this->assignedSubject->id,
            'created_by' => $this->developer->id,
        ]);
    }

    public function test_uploading_new_version_automatically_assigns_subject_reviewers()
    {
        $material = $this->assignedSubject->materials()->create([
            'type' => 'pdf',
            'created_by' => $this->developer->id,
        ]);

        $response = $this->actingAs($this->developer)->post(route('developer.materials.versions.store', $material), [
            'google_drive_id' => 'https://drive.google.com/file/d/123456789/view',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('developer.materials.show', $material));

        $this->assertDatabaseHas('material_versions', [
            'material_id' => $material->id,
            'version_number' => 1,
            'gdrive_file_id' => '123456789',
        ]);

        // Reviewer assigned to subject should automatically have a review created
        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $this->reviewer->id,
            'status' => 'draft',
        ]);
    }

    public function test_developer_subjects_index_does_not_show_subjects_from_deleted_trainings(): void
    {
        // Soft delete training
        $this->assignedSubject->training->delete();

        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 0)
        );
    }

    public function test_developer_cannot_view_subject_detail_when_training_is_deleted(): void
    {
        // Soft delete training (cascades to subject)
        $this->assignedSubject->training->delete();

        $response = $this->actingAs($this->developer)->get(route('developer.subjects.show', $this->assignedSubject));

        $response->assertNotFound();
    }

    public function test_deleting_training_cascades_soft_delete_to_subjects(): void
    {
        $training = $this->assignedSubject->training;
        $subjectId = $this->assignedSubject->id;

        $training->delete();

        $this->assertSoftDeleted('trainings', ['id' => $training->id]);
        $this->assertSoftDeleted('subjects', ['id' => $subjectId]);
    }

    public function test_developer_subjects_index_has_pagination(): void
    {
        $admin = User::role('admin')->first();
        $training = Training::create([
            'title' => 'Pelatihan Tambahan',
            'created_by' => $admin->id,
        ]);

        for ($i = 1; $i <= 14; $i++) {
            $subj = Subject::create([
                'training_id' => $training->id,
                'title' => "Mata Pelatihan Ke-{$i}",
                'sort_order' => $i + 2,
            ]);
            $subj->assignedUsers()->attach($this->developer->id, [
                'role' => 'pengembang',
                'assigned_by' => $admin->id,
            ]);
        }

        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 10)
            ->where('subjects.total', 15)
            ->has('subjects.links')
        );
    }

    public function test_developer_can_filter_subjects_by_search_or_training_name(): void
    {
        $admin = User::role('admin')->first();
        $training2 = Training::create([
            'title' => 'Pelatihan Kepemimpinan Pengawas',
            'created_by' => $admin->id,
        ]);

        $subj2 = Subject::create([
            'training_id' => $training2->id,
            'title' => 'Manajemen Mutu Pelayanan',
            'sort_order' => 1,
        ]);
        $subj2->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $admin->id,
        ]);

        // Filter by training name
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index', [
            'search' => 'Kepemimpinan',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 1)
            ->where('subjects.data.0.id', $subj2->id)
        );

        // Filter by subject title
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index', [
            'search' => 'Overview DAK',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 1)
            ->where('subjects.data.0.id', $this->assignedSubject->id)
        );
    }

    public function test_developer_can_filter_subjects_by_review_status(): void
    {
        $admin = User::role('admin')->first();
        $trainingDone = Training::create([
            'title' => 'Pelatihan Selesai',
            'status' => 'Selesai Review',
            'created_by' => $admin->id,
        ]);

        $subjDone = Subject::create([
            'training_id' => $trainingDone->id,
            'title' => 'Mata Pelatihan Selesai',
            'sort_order' => 1,
        ]);
        $subjDone->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $admin->id,
        ]);

        // Filter status Selesai Review
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index', [
            'status' => 'Selesai Review',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 1)
            ->where('subjects.data.0.id', $subjDone->id)
        );

        // Filter status Sedang Review
        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index', [
            'status' => 'Sedang Review',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->has('subjects.data', 1)
            ->where('subjects.data.0.id', $this->assignedSubject->id)
        );
    }

    public function test_developer_can_update_training_status_in_bulk(): void
    {
        $training = $this->assignedSubject->training;
        $this->assertEquals('Sedang Review', $training->status);

        $response = $this->actingAs($this->developer)->post(route('developer.trainings.status.bulk'), [
            'subject_ids' => [$this->assignedSubject->id],
            'status' => 'Selesai Review',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'status' => 'Selesai Review',
        ]);
    }

    public function test_developer_cannot_update_training_status_for_unassigned_subjects(): void
    {
        $admin = User::role('admin')->first();
        $unassignedTraining = Training::create([
            'title' => 'Pelatihan Orang Lain',
            'status' => 'Sedang Review',
            'created_by' => $admin->id,
        ]);
        $otherSubject = Subject::create([
            'training_id' => $unassignedTraining->id,
            'title' => 'Mata Pelatihan Lain',
            'sort_order' => 1,
        ]);
        // Do NOT assign to developer

        $response = $this->actingAs($this->developer)->post(route('developer.trainings.status.bulk'), [
            'subject_ids' => [$otherSubject->id],
            'status' => 'Selesai Review',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('trainings', [
            'id' => $unassignedTraining->id,
            'status' => 'Sedang Review',
        ]);
    }

    public function test_developer_subjects_index_shows_materials_and_reviewed_materials_count(): void
    {
        $admin = User::role('admin')->first();

        \App\Models\Material::factory()->create([
            'subject_id' => $this->assignedSubject->id,
            'status' => \App\Enums\MaterialStatus::Reviewed,
            'created_by' => $admin->id,
        ]);

        \App\Models\Material::factory()->create([
            'subject_id' => $this->assignedSubject->id,
            'status' => \App\Enums\MaterialStatus::InReview,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($this->developer)->get(route('developer.subjects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/subjects/index')
            ->where('subjects.data.0.materials_count', 2)
            ->where('subjects.data.0.reviewed_materials_count', 1)
        );
    }
}
