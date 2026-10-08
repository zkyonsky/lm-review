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
            ->where('reviews.data.0.material_version.material.title', 'MP 1')
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
}
