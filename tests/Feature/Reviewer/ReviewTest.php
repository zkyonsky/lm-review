<?php

namespace Tests\Feature\Reviewer;

use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $reviewer;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'reviewer']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer 1']);
        $this->reviewer->assignRole('reviewer');

        $training = Training::create([
            'code' => 'TR-01',
            'title' => 'E-Learning APIPDA',
            'created_by' => $admin->id,
        ]);

        $this->subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Overview DAK Fisik',
            'sort_order' => 1,
        ]);

        $material = $this->subject->materials()->create([
            'title' => 'MP 1',
            'type' => 'pdf',
            'created_by' => $admin->id,
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'created_by' => $admin->id,
        ]);
        $material->update(['current_version_id' => $version->id]);

        // Assign reviewer to subject
        $this->actingAs($admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->reviewer->id,
            'role' => 'reviewer',
        ]);
    }

    public function test_assigned_reviewer_sees_materials_in_reviews_index()
    {
        $response = $this->actingAs($this->reviewer)->get(route('reviewer.reviews.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('reviewer/reviews/index')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.material_version.material.title', 'Materi PDF')
            ->where('reviews.data.0.material_version.material.subject.title', 'Overview DAK Fisik')
        );
    }

    public function test_reviewer_can_post_comment_without_optional_fields()
    {
        $review = Review::where('reviewer_id', $this->reviewer->id)->first();

        $response = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.comments.store', $review), [
            'body' => 'Komentar pengujian untuk materi.',
            'anchor_type' => 'general',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Komentar berhasil ditambahkan.');

        $this->assertDatabaseHas('review_comments', [
            'review_id' => $review->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Komentar pengujian untuk materi.',
            'anchor_type' => 'general',
            'parent_id' => null,
            'page_number' => null,
        ]);
    }

    public function test_reviewer_can_reply_to_comment()
    {
        $review = Review::where('reviewer_id', $this->reviewer->id)->first();

        $comment = $review->comments()->create([
            'material_version_id' => $review->material_version_id,
            'user_id' => $this->reviewer->id,
            'body' => 'Komentar utama',
            'anchor_type' => 'general',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.comments.store', $review), [
            'body' => 'Balasan komentar utama',
            'parent_id' => $comment->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Balasan berhasil dikirim.');

        $this->assertDatabaseHas('review_comments', [
            'review_id' => $review->id,
            'parent_id' => $comment->id,
            'body' => 'Balasan komentar utama',
            'anchor_type' => 'general',
        ]);
    }

    public function test_reviewer_can_submit_review_and_cannot_add_new_root_comment()
    {
        $review = Review::where('reviewer_id', $this->reviewer->id)->first();

        $response = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.submit', $review), [
            'general_notes' => 'Catatan kesimpulan reviu materi.',
        ]);
        $response->assertRedirect();
        $this->assertEquals('submitted', $review->fresh()->status->value);
        $this->assertEquals('Catatan kesimpulan reviu materi.', $review->fresh()->general_notes);

        $responsePost = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.comments.store', $review), [
            'body' => 'Komentar setelah submit',
            'anchor_type' => 'general',
        ]);

        $responsePost->assertRedirect();
        $responsePost->assertSessionHas('error');
    }

    public function test_reviewer_cannot_submit_review_without_general_notes()
    {
        $review = Review::where('reviewer_id', $this->reviewer->id)->first();

        $response = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.submit', $review), [
            'general_notes' => '',
        ]);

        $response->assertSessionHasErrors(['general_notes']);
        $this->assertEquals('draft', $review->fresh()->status->value);
    }

    public function test_reviews_index_does_not_show_reviews_from_deleted_trainings(): void
    {
        // Soft delete the training
        $this->subject->training->delete();

        $response = $this->actingAs($this->reviewer)->get(route('reviewer.reviews.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('reviewer/reviews/index')
            ->has('reviews.data', 0)
        );
    }

    public function test_continuous_review_cycle_with_new_version_and_changelog(): void
    {
        $reviewV1 = Review::where('reviewer_id', $this->reviewer->id)->first();
        $material = $reviewV1->materialVersion->material;

        // 1. Reviewer posts comments on V1
        $this->actingAs($this->reviewer)->post(route('reviewer.workspace.comments.store', $reviewV1), [
            'body' => 'Catatan revisi untuk versi 1: perbaiki rumus pada modul.',
            'anchor_type' => 'general',
        ]);

        // 2. Reviewer submits review for V1
        $responseSubmit = $this->actingAs($this->reviewer)->post(route('reviewer.workspace.submit', $reviewV1), [
            'general_notes' => 'Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar',
        ]);
        $responseSubmit->assertRedirect();

        $reviewV1->refresh();
        $this->assertEquals('submitted', $reviewV1->status->value);
        $this->assertEquals('submitted', $reviewV1->assignment->status->value);
        $this->assertEquals('reviewed', $material->fresh()->status->value);

        // 3. Developer / Admin creates V2 with changelog
        $admin = User::role('admin')->first();
        $responseV2 = $this->actingAs($admin)->post(route('admin.materials.versions.store', $material), [
            'google_drive_id' => 'https://drive.google.com/file/d/new-file-id/view',
            'is_active' => true,
            'changelog' => 'Pembaruan materi bab 3 dan perbaikan rumus sesuai catatan reviewer periode sebelumnya.',
        ]);
        $responseV2->assertRedirect();

        $material->refresh();
        $this->assertEquals('in_review', $material->status->value);

        $versionV2 = $material->currentVersion;
        $this->assertEquals(2, $versionV2->version_number);
        $this->assertEquals('Pembaruan materi bab 3 dan perbaikan rumus sesuai catatan reviewer periode sebelumnya.', $versionV2->changelog);

        // 4. Verify assignment is reset to pending for the new cycle
        $assignment = $material->assignments()->where('reviewer_id', $this->reviewer->id)->first();
        $this->assertEquals('pending', $assignment->status->value);

        // 5. Verify new Review for V2 is created
        $reviewV2 = Review::where('material_version_id', $versionV2->id)
            ->where('reviewer_id', $this->reviewer->id)
            ->first();
        $this->assertNotNull($reviewV2);
        $this->assertEquals('draft', $reviewV2->status->value);

        // 6. Reviewer opens workspace for V2 and sees previous reviews history
        $responseWorkspaceV2 = $this->actingAs($this->reviewer)->get(route('reviewer.workspace.show', $reviewV2));
        $responseWorkspaceV2->assertOk();
        $responseWorkspaceV2->assertInertia(fn ($page) => $page
            ->component('reviewer/workspace/index')
            ->where('review.material_version.version_number', 2)
            ->where('review.material_version.changelog', 'Pembaruan materi bab 3 dan perbaikan rumus sesuai catatan reviewer periode sebelumnya.')
            ->has('previousReviews', 1)
            ->where('previousReviews.0.id', $reviewV1->id)
            ->where('previousReviews.0.general_notes', 'Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar')
            ->has('previousReviews.0.comments', 1)
        );
    }

    public function test_reviewer_sees_previous_period_review_history_on_same_material_version(): void
    {
        $admin = User::role('admin')->first();

        // Clean up initial setup reviews to have isolated test scenario
        Review::where('reviewer_id', $this->reviewer->id)->delete();
        \App\Models\ReviewAssignment::where('reviewer_id', $this->reviewer->id)->delete();

        // 1. Setup period 1
        $period1 = \App\Models\ReviewPeriod::create([
            'name' => 'Periode 2025',
            'year' => 2025,
            'status' => \App\Enums\ReviewPeriodStatus::Closed->value,
            'created_by' => $admin->id,
        ]);

        $material = $this->subject->materials()->first();
        $version = $material->currentVersion;

        // Period 1 review
        $assign1 = \App\Models\ReviewAssignment::create([
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period1->id,
            'assigned_by' => $admin->id,
            'status' => \App\Enums\AssignmentStatus::Submitted,
        ]);

        $review1 = Review::create([
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assign1->id,
            'status' => \App\Enums\ReviewStatus::Submitted,
            'conclusion' => \App\Enums\ReviewConclusion::Relevant,
            'general_notes' => 'Catatan evaluasi kurikulum 2025: materi sudah lengkap dan relevan.',
            'submitted_at' => now()->subYear(),
        ]);

        ReviewComment::create([
            'material_version_id' => $version->id,
            'review_id' => $review1->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Komentar catatan modul bab 1 pada periode 2025.',
            'anchor_type' => 'general',
            'status' => \App\Enums\CommentStatus::Addressed,
        ]);

        // 2. Setup period 2 (same version 1, re-enrolled)
        $period2 = \App\Models\ReviewPeriod::create([
            'name' => 'Periode 2026',
            'year' => 2026,
            'status' => \App\Enums\ReviewPeriodStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        $assign2 = \App\Models\ReviewAssignment::create([
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
            'review_period_id' => $period2->id,
            'assigned_by' => $admin->id,
            'status' => \App\Enums\AssignmentStatus::InProgress,
        ]);

        $review2 = Review::create([
            'material_version_id' => $version->id, // SAME version!
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assign2->id,
            'status' => \App\Enums\ReviewStatus::Draft,
        ]);

        // 3. Open workspace for Period 2 review
        $response = $this->actingAs($this->reviewer)->get(route('reviewer.workspace.show', $review2));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('reviewer/workspace/index')
            ->where('review.id', $review2->id)
            ->where('review.material_version.version_number', 1)
            ->has('previousReviews', 1)
            ->where('previousReviews.0.id', $review1->id)
            ->where('previousReviews.0.assignment.period.name', 'Periode 2025')
            ->where('previousReviews.0.general_notes', 'Catatan evaluasi kurikulum 2025: materi sudah lengkap dan relevan.')
            ->has('previousReviews.0.comments', 1)
        );
    }
}
