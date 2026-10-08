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
            ->has('subjects', 1)
            ->where('subjects.0.id', $this->assignedSubject->id)
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
            'title' => 'Modul 1 Pengenalan',
            'type' => 'pdf',
            'description' => 'Deskripsi materi pengenalan',
            'order' => 1,
        ]);

        $response->assertRedirect(route('developer.subjects.show', $this->assignedSubject));
        $this->assertDatabaseHas('materials', [
            'subject_id' => $this->assignedSubject->id,
            'title' => 'Modul 1 Pengenalan',
            'created_by' => $this->developer->id,
        ]);
    }

    public function test_uploading_new_version_automatically_assigns_subject_reviewers()
    {
        $material = $this->assignedSubject->materials()->create([
            'title' => 'Modul PDF',
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
}
