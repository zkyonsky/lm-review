<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewComment;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reviewer;
    protected Subject $subject;
    protected Material $material;
    protected MaterialVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'reviewer']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer Ahli']);
        $this->reviewer->assignRole('reviewer');

        $training = Training::create([
            'code' => 'TR-ADM-01',
            'title' => 'Pelatihan Pengelolaan Review',
            'created_by' => $this->admin->id,
        ]);

        $this->subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan 1',
            'sort_order' => 1,
        ]);

        $this->material = $this->subject->materials()->create([
            'title' => 'Materi Pembelajaran A',
            'type' => 'pdf',
            'created_by' => $this->admin->id,
        ]);

        $this->version = $this->material->versions()->create([
            'version_number' => 1,
            'gdrive_file_id' => 'dummy_file_id_123',
            'gdrive_url' => 'https://drive.google.com/file/d/dummy_file_id_123/view',
            'created_by' => $this->admin->id,
        ]);
        $this->material->update(['current_version_id' => $this->version->id]);
    }

    public function test_admin_version_workspace_redirects_to_existing_review_workspace(): void
    {
        $assignment = ReviewAssignment::create([
            'material_id' => $this->material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $review = Review::create([
            'material_version_id' => $this->version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assignment->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.versions.workspace', $this->version));

        $response->assertRedirect(route('admin.workspace.show', $review));
    }

    public function test_admin_version_workspace_auto_assigns_subject_reviewer_if_not_assigned_yet(): void
    {
        // Attach reviewer to subject
        $this->subject->reviewers()->attach($this->reviewer->id, ['role' => 'reviewer']);

        // Access version workspace without pre-existing review
        $response = $this->actingAs($this->admin)->get(route('admin.versions.workspace', $this->version));

        $createdReview = Review::where('material_version_id', $this->version->id)
            ->where('reviewer_id', $this->reviewer->id)
            ->first();

        $this->assertNotNull($createdReview);
        $response->assertRedirect(route('admin.workspace.show', $createdReview));
    }

    public function test_admin_version_workspace_redirects_to_version_page_if_no_reviewer_at_all(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.versions.workspace', $this->version));

        $response->assertRedirect(route('admin.versions.show', $this->version));
        $response->assertSessionHas('error');
    }

    public function test_admin_can_view_workspace_with_preview_and_reviewer_comments(): void
    {
        $assignment = ReviewAssignment::create([
            'material_id' => $this->material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $review = Review::create([
            'material_version_id' => $this->version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assignment->id,
            'status' => 'submitted',
        ]);

        $comment = $review->comments()->create([
            'material_version_id' => $this->version->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Perbaiki slide nomor 5.',
            'anchor_type' => 'general',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.workspace.show', $review));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/workspace/index')
            ->where('review.id', $review->id)
            ->where('review.material_version.material.title', 'Materi Pembelajaran A')
            ->has('review.comments', 1)
            ->where('review.comments.0.body', 'Perbaiki slide nomor 5.')
            ->has('allReviews', 1)
        );
    }

    public function test_admin_can_reply_to_comment_in_workspace(): void
    {
        $assignment = ReviewAssignment::create([
            'material_id' => $this->material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $review = Review::create([
            'material_version_id' => $this->version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assignment->id,
            'status' => 'submitted',
        ]);

        $comment = $review->comments()->create([
            'material_version_id' => $this->version->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Perlu ditambahkan referensi buku.',
            'anchor_type' => 'general',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.workspace.comments.reply', [$review, $comment]), [
            'body' => 'Siap, referensi sudah ditambahkan.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('review_comments', [
            'parent_id' => $comment->id,
            'user_id' => $this->admin->id,
            'body' => 'Siap, referensi sudah ditambahkan.',
        ]);
    }

    public function test_admin_can_toggle_comment_status_in_workspace(): void
    {
        $assignment = ReviewAssignment::create([
            'material_id' => $this->material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $review = Review::create([
            'material_version_id' => $this->version->id,
            'reviewer_id' => $this->reviewer->id,
            'review_assignment_id' => $assignment->id,
            'status' => 'submitted',
        ]);

        $comment = $review->comments()->create([
            'material_version_id' => $this->version->id,
            'user_id' => $this->reviewer->id,
            'body' => 'Komentar untuk ditandai selesai.',
            'anchor_type' => 'general',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.workspace.comments.toggle', [$review, $comment]));

        $response->assertRedirect();
        $this->assertEquals('addressed', $comment->fresh()->status->value);
    }
}
