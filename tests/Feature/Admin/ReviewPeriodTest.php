<?php

namespace Tests\Feature\Admin;

use App\Enums\AssignmentStatus;
use App\Enums\MaterialStatus;
use App\Enums\ReviewConclusion;
use App\Enums\ReviewPeriodStatus;
use App\Enums\ReviewStatus;
use App\Models\Material;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewPeriod;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reviewer;
    protected User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('reviewer');
        Role::findOrCreate('developer');

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer User']);
        $this->reviewer->assignRole('reviewer');

        $this->developer = User::factory()->create(['name' => 'Developer User']);
        $this->developer->assignRole('developer');
    }

    public function test_admin_can_view_review_periods_index(): void
    {
        ReviewPeriod::create([
            'name' => 'Periode Reviu 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'description' => 'Fokus pada kurikulum baru',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.periods.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/periods/index')
            ->has('periods.data', 1)
            ->where('periods.data.0.name', 'Periode Reviu 2026')
            ->where('periods.data.0.year', 2026)
        );
    }

    public function test_admin_can_create_review_period(): void
    {
        $payload = [
            'name' => 'Periode Semester 1 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'description' => 'Reviu regulasi PMK 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.periods.store'), $payload);

        $response->assertRedirect(route('admin.periods.index'));
        $this->assertDatabaseHas('review_periods', [
            'name' => 'Periode Semester 1 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_review_period(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Awal',
            'year' => 2025,
            'status' => ReviewPeriodStatus::Draft->value,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.periods.index'))
            ->put(route('admin.periods.update', $period), [
                'name' => 'Periode Diperbarui',
                'year' => 2026,
                'status' => ReviewPeriodStatus::Active->value,
                'description' => 'Deskripsi baru',
            ]);

        $response->assertRedirect(route('admin.periods.index'));
        $this->assertDatabaseHas('review_periods', [
            'id' => $period->id,
            'name' => 'Periode Diperbarui',
            'status' => ReviewPeriodStatus::Active->value,
        ]);
    }

    public function test_admin_can_delete_review_period(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Dihapus',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Draft->value,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.periods.destroy', $period));

        $response->assertRedirect(route('admin.periods.index'));
        $this->assertSoftDeleted('review_periods', [
            'id' => $period->id,
        ]);
    }

    public function test_admin_can_enroll_trainings_into_review_period(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Siklus Evaluasi Kebijakan 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'code' => 'TRN-2026-01',
            'title' => 'Pelatihan Perbendaharaan Negara',
            'created_by' => $this->admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan DAK Fisik',
            'sort_order' => 1,
        ]);

        // Assign reviewer to subject
        $subject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        $material = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Modul Penyaluran DAK',
            'type' => 'pdf',
            'created_by' => $this->developer->id,
            'status' => MaterialStatus::Reviewed, // previously already reviewed
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'created_by' => $this->developer->id,
        ]);
        $material->update(['current_version_id' => $version->id]);

        // Enroll training into the period
        $response = $this->actingAs($this->admin)->post(route('admin.periods.enroll', $period), [
            'training_ids' => [$training->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check pivot
        $this->assertDatabaseHas('review_period_training', [
            'review_period_id' => $period->id,
            'training_id' => $training->id,
        ]);

        // Check assignment created with review_period_id
        $this->assertDatabaseHas('review_assignments', [
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period->id,
            'status' => AssignmentStatus::Pending->value,
        ]);

        // Check draft review created for version 1
        $this->assertDatabaseHas('reviews', [
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'status' => ReviewStatus::Draft->value,
        ]);

        // Check material status updated to in_review
        $this->assertEquals(MaterialStatus::InReview, $material->fresh()->status);
    }

    public function test_reviewer_submitting_with_relevant_conclusion_sets_material_to_reviewed(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'code' => 'TRN-02',
            'title' => 'Pelatihan Penganggaran',
            'created_by' => $this->admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Pengantar Penganggaran',
            'sort_order' => 1,
        ]);

        $material = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Slide Paparan',
            'type' => 'pdf',
            'created_by' => $this->developer->id,
            'status' => MaterialStatus::InReview,
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'created_by' => $this->developer->id,
        ]);
        $material->update(['current_version_id' => $version->id]);

        $assignment = ReviewAssignment::create([
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::InProgress,
        ]);

        $review = Review::create([
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assignment->id,
            'status' => ReviewStatus::Draft,
        ]);

        $response = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.submit', $review), [
            'general_notes' => 'Materi sudah sangat sesuai dan masih relevan dengan kebijakan saat ini.',
            'conclusion' => ReviewConclusion::Relevant->value,
        ]);

        $response->assertRedirect();
        $review->refresh();
        $this->assertEquals(ReviewStatus::Submitted, $review->status);
        $this->assertEquals(ReviewConclusion::Relevant, $review->conclusion);
        $this->assertEquals(AssignmentStatus::Submitted, $assignment->fresh()->status);
        $this->assertEquals(MaterialStatus::Reviewed, $material->fresh()->status);
    }

    public function test_reviewer_submitting_with_needs_revision_sets_material_to_revising(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'code' => 'TRN-03',
            'title' => 'Pelatihan Akuntansi',
            'created_by' => $this->admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Standar Akuntansi',
            'sort_order' => 1,
        ]);

        $material = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Modul SAP',
            'type' => 'pdf',
            'created_by' => $this->developer->id,
            'status' => MaterialStatus::InReview,
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'created_by' => $this->developer->id,
        ]);
        $material->update(['current_version_id' => $version->id]);

        $assignment = ReviewAssignment::create([
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::InProgress,
        ]);

        $review = Review::create([
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assignment->id,
            'status' => ReviewStatus::Draft,
        ]);

        $response = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.submit', $review), [
            'general_notes' => 'Terdapat perubahan bagan akun standar pada PMK terbaru, mohon lakukan update modul.',
            'conclusion' => ReviewConclusion::NeedsRevision->value,
        ]);

        $response->assertRedirect();
        $review->refresh();
        $this->assertEquals(ReviewStatus::Submitted, $review->status);
        $this->assertEquals(ReviewConclusion::NeedsRevision, $review->conclusion);
        $this->assertEquals(AssignmentStatus::Submitted, $assignment->fresh()->status);
        $this->assertEquals(MaterialStatus::Revising, $material->fresh()->status);
    }

    public function test_reviewed_materials_count_filters_by_period_id(): void
    {
        $period2025 = ReviewPeriod::create([
            'name' => 'Periode 2025',
            'year' => 2025,
            'status' => ReviewPeriodStatus::Closed->value,
            'created_by' => $this->admin->id,
        ]);

        $period2026 = ReviewPeriod::create([
            'name' => 'Periode 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'code' => 'TRN-04',
            'title' => 'Pelatihan DAK',
            'created_by' => $this->admin->id,
        ]);
        $training->periods()->attach([$period2025->id, $period2026->id]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan 1',
            'sort_order' => 1,
        ]);

        $material1 = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Materi 1',
            'type' => 'pdf',
            'created_by' => $this->developer->id,
            'status' => MaterialStatus::Reviewed,
        ]);

        $material2 = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Materi 2',
            'type' => 'pdf',
            'created_by' => $this->developer->id,
            'status' => MaterialStatus::InReview,
        ]);

        // Material 1 was reviewed in 2025
        ReviewAssignment::create([
            'material_id' => $material1->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period2025->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::Submitted,
        ]);

        // Material 2 was reviewed in 2026
        ReviewAssignment::create([
            'material_id' => $material2->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period2026->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::Submitted,
        ]);

        // Filter by 2025 period
        $response2025 = $this->actingAs($this->admin)->get(route('admin.trainings.index', [
            'period_id' => $period2025->id,
        ]));
        $response2025->assertOk();
        $response2025->assertInertia(fn ($page) => $page
            ->where('trainings.data.0.reviewed_materials_count', 1)
            ->where('trainings.data.0.materials_count', 2)
        );

        // Filter by 2026 period
        $response2026 = $this->actingAs($this->admin)->get(route('admin.trainings.index', [
            'period_id' => $period2026->id,
        ]));
        $response2026->assertOk();
        $response2026->assertInertia(fn ($page) => $page
            ->where('trainings.data.0.reviewed_materials_count', 1)
            ->where('trainings.data.0.materials_count', 2)
        );
    }

    public function test_admin_can_view_period_show_with_enrolled_trainings(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Uji Coba 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $training1 = Training::create([
            'title' => 'Pelatihan Anggaran Publik',
            'created_by' => $this->admin->id,
        ]);
        $training2 = Training::create([
            'title' => 'Pelatihan Manajemen Risiko',
            'created_by' => $this->admin->id,
        ]);

        $period->trainings()->attach($training1->id);

        $response = $this->actingAs($this->admin)->get(route('admin.periods.show', $period));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/periods/show')
            ->where('period.name', 'Periode Uji Coba 2026')
            ->has('trainings.data', 1)
            ->where('trainings.data.0.title', 'Pelatihan Anggaran Publik')
            ->has('availableTrainings', 1)
            ->where('availableTrainings.0.title', 'Pelatihan Manajemen Risiko')
        );
    }

    public function test_admin_can_remove_training_from_period(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Hapus Pelatihan 2026',
            'year' => 2026,
            'status' => ReviewPeriodStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'title' => 'Pelatihan Yang Dikeluarkan',
            'created_by' => $this->admin->id,
        ]);

        $period->trainings()->attach($training->id);
        $this->assertCount(1, $period->fresh()->trainings);

        $response = $this->actingAs($this->admin)->delete(route('admin.periods.trainings.remove', [
            'period' => $period,
            'training' => $training,
        ]));

        $response->assertRedirect();
        $this->assertCount(0, $period->fresh()->trainings);
    }
}
