<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\MaterialStatus;
use App\Enums\ReviewStatus;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::findOrCreate('admin');
        \Spatie\Permission\Models\Role::findOrCreate('pengembang');
        \Spatie\Permission\Models\Role::findOrCreate('reviewer');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_reviewer_can_visit_the_dashboard()
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        $response = $this->actingAs($reviewer)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_developer_can_visit_the_dashboard()
    {
        $developer = User::factory()->create();
        $developer->assignRole('pengembang');

        $response = $this->actingAs($developer)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_reviewer_pending_and_completed_tasks_exclude_soft_deleted_trainings()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        // Training 1: Active training with 1 pending task and 1 completed task
        $period = \App\Models\ReviewPeriod::create([
            'name' => 'Periode Aktif 2026',
            'year' => 2026,
            'status' => \App\Enums\ReviewPeriodStatus::Active,
            'created_by' => $admin->id,
        ]);

        $activeTraining = Training::create([
            'code' => 'TRN-ACTIVE',
            'title' => 'Pelatihan Aktif',
            'created_by' => $admin->id,
        ]);
        $activeTraining->periods()->attach($period->id);

        $activeSubject = Subject::create([
            'training_id' => $activeTraining->id,
            'title' => 'Mata Pelatihan Aktif',
            'sort_order' => 1,
        ]);

        $activeMatPending = Material::create([
            'subject_id' => $activeSubject->id,
            'title' => 'Materi Pending',
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::InReview,
        ]);
        $versionPending = $activeMatPending->versions()->create([
            'version_number' => 1,
            'created_by' => $admin->id,
        ]);
        $activeMatPending->update(['current_version_id' => $versionPending->id]);
        $assignPending = ReviewAssignment::create([
            'material_id' => $activeMatPending->id,
            'reviewer_id' => $reviewer->id,
            'assigned_by' => $admin->id,
            'status' => AssignmentStatus::InProgress,
        ]);
        Review::create([
            'material_version_id' => $versionPending->id,
            'reviewer_id' => $reviewer->id,
            'review_assignment_id' => $assignPending->id,
            'status' => ReviewStatus::Draft,
        ]);

        $activeMatCompleted = Material::create([
            'subject_id' => $activeSubject->id,
            'title' => 'Materi Selesai',
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::Reviewed,
        ]);
        $versionCompleted = $activeMatCompleted->versions()->create([
            'version_number' => 1,
            'created_by' => $admin->id,
        ]);
        $activeMatCompleted->update(['current_version_id' => $versionCompleted->id]);
        $assignCompleted = ReviewAssignment::create([
            'material_id' => $activeMatCompleted->id,
            'reviewer_id' => $reviewer->id,
            'assigned_by' => $admin->id,
            'status' => AssignmentStatus::Submitted,
        ]);
        Review::create([
            'material_version_id' => $versionCompleted->id,
            'reviewer_id' => $reviewer->id,
            'review_assignment_id' => $assignCompleted->id,
            'status' => ReviewStatus::Submitted,
        ]);

        // Training 2: Soft deleted training with 1 draft review and 1 submitted review
        $deletedTraining = Training::create([
            'code' => 'TRN-DELETED',
            'title' => 'Pelatihan Dihapus',
            'created_by' => $admin->id,
        ]);
        $deletedSubject = Subject::create([
            'training_id' => $deletedTraining->id,
            'title' => 'Mata Pelatihan Dihapus',
            'sort_order' => 1,
        ]);
        $deletedMat = Material::create([
            'subject_id' => $deletedSubject->id,
            'title' => 'Materi Dihapus',
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::InReview,
        ]);
        $deletedVersion = $deletedMat->versions()->create([
            'version_number' => 1,
            'created_by' => $admin->id,
        ]);
        $deletedMat->update(['current_version_id' => $deletedVersion->id]);
        $deletedAssign = ReviewAssignment::create([
            'material_id' => $deletedMat->id,
            'reviewer_id' => $reviewer->id,
            'assigned_by' => $admin->id,
            'status' => AssignmentStatus::InProgress,
        ]);
        Review::create([
            'material_version_id' => $deletedVersion->id,
            'reviewer_id' => $reviewer->id,
            'review_assignment_id' => $deletedAssign->id,
            'status' => ReviewStatus::Draft,
        ]);

        // Soft delete training 2
        $deletedTraining->delete();

        // Visit dashboard as reviewer
        $response = $this->actingAs($reviewer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.reviewer.pending_tasks', 1)
            ->where('stats.reviewer.completed_tasks', 1)
        );
    }

    public function test_reviewer_dashboard_only_counts_trainings_in_active_period()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        $closedPeriod = \App\Models\ReviewPeriod::create([
            'name' => 'Periode Ditutup',
            'year' => 2025,
            'status' => \App\Enums\ReviewPeriodStatus::Closed,
            'created_by' => $admin->id,
        ]);

        $closedTraining = Training::create([
            'title' => 'Pelatihan Periode Lama',
            'created_by' => $admin->id,
        ]);
        $closedTraining->periods()->attach($closedPeriod->id);

        $closedSubject = Subject::create([
            'training_id' => $closedTraining->id,
            'title' => 'Mata Pelatihan Periode Lama',
            'sort_order' => 1,
        ]);

        $closedMat = Material::create([
            'subject_id' => $closedSubject->id,
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::InReview,
        ]);
        $closedVer = $closedMat->versions()->create([
            'version_number' => 1,
            'created_by' => $admin->id,
        ]);
        $closedMat->update(['current_version_id' => $closedVer->id]);
        $closedAssign = ReviewAssignment::create([
            'review_period_id' => $closedPeriod->id,
            'material_id' => $closedMat->id,
            'reviewer_id' => $reviewer->id,
            'assigned_by' => $admin->id,
            'status' => AssignmentStatus::Pending,
        ]);
        Review::create([
            'material_version_id' => $closedVer->id,
            'reviewer_id' => $reviewer->id,
            'review_assignment_id' => $closedAssign->id,
            'status' => ReviewStatus::Draft,
        ]);

        // Reviewer visits dashboard - closed period tasks should NOT be counted
        $response = $this->actingAs($reviewer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.reviewer.pending_tasks', 0)
            ->where('stats.reviewer.completed_tasks', 0)
        );
    }

    public function test_developer_dashboard_only_counts_trainings_in_active_period()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $developer = User::factory()->create();
        $developer->assignRole('pengembang');

        $activePeriod = \App\Models\ReviewPeriod::create([
            'name' => 'Periode Aktif 2026',
            'year' => 2026,
            'status' => \App\Enums\ReviewPeriodStatus::Active,
            'created_by' => $admin->id,
        ]);

        $closedPeriod = \App\Models\ReviewPeriod::create([
            'name' => 'Periode Ditutup 2025',
            'year' => 2025,
            'status' => \App\Enums\ReviewPeriodStatus::Closed,
            'created_by' => $admin->id,
        ]);

        // Training in active period
        $activeTraining = Training::create([
            'title' => 'Pelatihan Aktif',
            'created_by' => $admin->id,
        ]);
        $activeTraining->periods()->attach($activePeriod->id);
        $activeSubject = Subject::create([
            'training_id' => $activeTraining->id,
            'title' => 'Mata Pelatihan Aktif',
            'sort_order' => 1,
        ]);
        $activeSubject->assignedUsers()->attach($developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $admin->id,
        ]);
        $mat1 = Material::create([
            'subject_id' => $activeSubject->id,
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::Reviewed,
        ]);
        $mat2 = Material::create([
            'subject_id' => $activeSubject->id,
            'type' => 'video',
            'created_by' => $admin->id,
            'status' => MaterialStatus::InReview,
        ]);

        // Training in closed period
        $closedTraining = Training::create([
            'title' => 'Pelatihan Closed',
            'created_by' => $admin->id,
        ]);
        $closedTraining->periods()->attach($closedPeriod->id);
        $closedSubject = Subject::create([
            'training_id' => $closedTraining->id,
            'title' => 'Mata Pelatihan Closed',
            'sort_order' => 1,
        ]);
        $closedSubject->assignedUsers()->attach($developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $admin->id,
        ]);
        Material::create([
            'subject_id' => $closedSubject->id,
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::Reviewed,
        ]);

        // Training without any period
        $noPeriodTraining = Training::create([
            'title' => 'Pelatihan Tanpa Periode',
            'created_by' => $admin->id,
        ]);
        $noPeriodSubject = Subject::create([
            'training_id' => $noPeriodTraining->id,
            'title' => 'Mata Pelatihan Tanpa Periode',
            'sort_order' => 1,
        ]);
        $noPeriodSubject->assignedUsers()->attach($developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $admin->id,
        ]);
        Material::create([
            'subject_id' => $noPeriodSubject->id,
            'type' => 'pdf',
            'created_by' => $admin->id,
            'status' => MaterialStatus::Draft,
        ]);

        $response = $this->actingAs($developer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.developer.total_subjects', 1)
            ->where('stats.developer.total_materials', 2)
            ->where('stats.developer.reviewed_materials', 1)
        );
    }
}
