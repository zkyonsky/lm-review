<?php

namespace Tests\Feature\Admin;

use App\Enums\AssignmentStatus;
use App\Enums\CommentAnchor;
use App\Enums\CommentStatus;
use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Enums\ReviewPeriodStatus;
use App\Enums\ReviewStatus;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewComment;
use App\Models\ReviewPeriod;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('reviewer');

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer User']);
        $this->reviewer->assignRole('reviewer');
    }

    public function test_admin_can_export_comments_for_review_period(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Reviu Q1 2026',
            'year' => 2026,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => ReviewPeriodStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'title' => 'Pelatihan AI',
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Dasar LLM',
        ]);

        $material = Material::create([
            'subject_id' => $subject->id,
            'type' => MaterialType::Pdf,
            'status' => MaterialStatus::InReview,
            'created_by' => $this->admin->id,
        ]);

        $version = MaterialVersion::create([
            'material_id' => $material->id,
            'version_number' => 1,
            'file_path' => 'materials/modul1.pdf',
            'file_name' => 'modul1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'created_by' => $this->admin->id,
        ]);

        $assignment = ReviewAssignment::create([
            'review_period_id' => $period->id,
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::Submitted,
        ]);

        $review = Review::create([
            'review_assignment_id' => $assignment->id,
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'status' => ReviewStatus::Submitted,
        ]);

        ReviewComment::create([
            'material_version_id' => $version->id,
            'review_id' => $review->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Perbaiki tipografi pada halaman 5.',
            'anchor_type' => CommentAnchor::Page,
            'page_number' => 5,
            'status' => CommentStatus::Open,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.comments', ['period_id' => $period->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('laporan_ulasan_reviu-q1-2026_', $response->headers->get('Content-Disposition'));

        // Streamed content check
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Perbaiki tipografi pada halaman 5.', $content);
        $this->assertStringContainsString('Pelatihan AI', $content);
        $this->assertStringContainsString('Dasar LLM', $content);
        $this->assertStringContainsString('Materi PDF', $content);
        $this->assertStringContainsString('Reviewer User', $content);
    }

    public function test_admin_can_export_comments_for_period_and_training(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Reviu Khusus',
            'year' => 2026,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => ReviewPeriodStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'title' => 'Pelatihan Web',
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Frontend React',
        ]);

        $material = Material::create([
            'subject_id' => $subject->id,
            'type' => MaterialType::Video,
            'status' => MaterialStatus::InReview,
            'created_by' => $this->admin->id,
        ]);

        $version = MaterialVersion::create([
            'material_id' => $material->id,
            'version_number' => 1,
            'file_path' => 'materials/video.mp4',
            'file_name' => 'video.mp4',
            'file_size' => 2048,
            'mime_type' => 'video/mp4',
            'created_by' => $this->admin->id,
        ]);

        $assignment = ReviewAssignment::create([
            'review_period_id' => $period->id,
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::Submitted,
        ]);

        $review = Review::create([
            'review_assignment_id' => $assignment->id,
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'status' => ReviewStatus::Submitted,
        ]);

        ReviewComment::create([
            'material_version_id' => $version->id,
            'review_id' => $review->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Audio kurang jernih.',
            'anchor_type' => CommentAnchor::General,
            'status' => CommentStatus::Open,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.comments', [
                'period_id' => $period->id,
                'training_id' => $training->id,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('laporan_ulasan_reviu-khusus_pelatihan-web_', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Audio kurang jernih.', $content);
        $this->assertStringContainsString('Pelatihan Web', $content);
    }

    public function test_admin_can_export_report_including_unreviewed_materials(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Belum Selesai',
            'year' => 2026,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => ReviewPeriodStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'title' => 'Pelatihan Belum Direviu',
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);
        $period->trainings()->attach($training->id);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan Umum',
        ]);

        // Materi 1: Ditugaskan tapi belum direviu
        $material1 = Material::create([
            'subject_id' => $subject->id,
            'type' => MaterialType::Pdf,
            'status' => MaterialStatus::InReview,
            'created_by' => $this->admin->id,
        ]);

        $version1 = MaterialVersion::create([
            'material_id' => $material1->id,
            'version_number' => 1,
            'file_path' => 'materials/m1.pdf',
            'file_size' => 1024,
            'created_by' => $this->admin->id,
        ]);

        ReviewAssignment::create([
            'review_period_id' => $period->id,
            'material_id' => $material1->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::Pending,
        ]);

        // Materi 2: Belum ditugaskan sama sekali
        $material2 = Material::create([
            'subject_id' => $subject->id,
            'type' => MaterialType::Video,
            'status' => MaterialStatus::Draft,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.comments', ['period_id' => $period->id]));

        $response->assertOk();

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Pastikan muncul materi yang belum direviu dan belum ditugaskan
        $this->assertStringContainsString('Pelatihan Belum Direviu', $content);
        $this->assertStringContainsString('Belum Direviu', $content);
        $this->assertStringContainsString('Belum Ditugaskan', $content);
        $this->assertStringContainsString('Reviewer User', $content);
    }

    public function test_reviewer_assigned_at_subject_level_is_displayed_in_report(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Subject Reviewer',
            'year' => 2026,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => ReviewPeriodStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'title' => 'Pelatihan Microlearning',
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);
        $period->trainings()->attach($training->id);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan Khusus',
        ]);

        // Reviewer ditugaskan di tingkat Subject
        $subject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        // Materi tidak memiliki row ReviewAssignment eksplisit
        $material = Material::create([
            'subject_id' => $subject->id,
            'type' => MaterialType::Video,
            'status' => MaterialStatus::InReview,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.comments', ['period_id' => $period->id]));

        $response->assertOk();

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Nama reviewer harus muncul dan status 'Belum Direviu', tidak boleh '-' atau 'Belum Ditugaskan'
        $this->assertStringContainsString('Reviewer User', $content);
        $this->assertStringContainsString('Belum Direviu', $content);
        $this->assertStringNotContainsString('Belum Ditugaskan', $content);
    }

    public function test_reviewer_with_null_review_period_id_is_displayed_in_period_export(): void
    {
        $period = ReviewPeriod::create([
            'name' => 'Periode Penugasan Umum',
            'year' => 2026,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => ReviewPeriodStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $training = Training::create([
            'title' => 'Pelatihan Akuntansi',
            'year' => 2026,
            'created_by' => $this->admin->id,
        ]);
        $period->trainings()->attach($training->id);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Bagan Akun',
        ]);

        $material = Material::create([
            'subject_id' => $subject->id,
            'type' => MaterialType::Pdf,
            'status' => MaterialStatus::InReview,
            'created_by' => $this->admin->id,
        ]);

        // Assignment dibuat dengan review_period_id = NULL
        ReviewAssignment::create([
            'review_period_id' => null,
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => AssignmentStatus::Pending,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.comments', ['period_id' => $period->id]));

        $response->assertOk();

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Harus menampilkan reviewer dan tidak boleh '-'
        $this->assertStringContainsString('Reviewer User', $content);
        $this->assertStringContainsString('Belum Direviu', $content);
        $this->assertStringNotContainsString('Belum Ditugaskan', $content);
    }
}
