<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewComment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewWorkspaceController extends Controller
{
    public function showForVersion(Request $request, MaterialVersion $version)
    {
        $version->load(['material.subject.reviewers', 'reviews.user']);

        if ($request->filled('review_id')) {
            $review = $version->reviews()->where('id', $request->query('review_id'))->first();
            if ($review) {
                return redirect()->route('admin.workspace.show', $review);
            }
        }

        $review = $version->reviews()->where('status', 'submitted')->latest('updated_at')->first()
            ?? $version->reviews()->latest('updated_at')->first();

        if ($review) {
            return redirect()->route('admin.workspace.show', $review);
        }

        $subjectReviewers = $version->material->subject->reviewers;
        if ($subjectReviewers->isNotEmpty()) {
            $firstReview = null;
            foreach ($subjectReviewers as $reviewer) {
                $assignment = ReviewAssignment::firstOrCreate([
                    'material_id' => $version->material_id,
                    'reviewer_id' => $reviewer->id,
                ], [
                    'assigned_by' => auth()->id(),
                    'status' => 'pending',
                ]);

                $createdReview = Review::firstOrCreate([
                    'material_version_id' => $version->id,
                    'reviewer_id' => $reviewer->id,
                ], [
                    'review_assignment_id' => $assignment->id,
                    'status' => 'draft',
                ]);

                if (!$firstReview) {
                    $firstReview = $createdReview;
                }
            }

            if ($firstReview) {
                return redirect()->route('admin.workspace.show', $firstReview);
            }
        }

        return redirect()->route('admin.versions.show', $version)
            ->with('error', 'Belum ada reviewer yang ditugaskan pada versi materi ini. Silakan pilih dan tugaskan reviewer terlebih dahulu.');
    }

    public function show(Review $review)
    {
        $review->load([
            'materialVersion.material.subject.training', 
            'materialVersion.scormPackage.scos',
            'user',
            'comments' => function ($query) {
                $query->root()->with(['replies.user', 'user', 'sco']);
            }
        ]);

        $allReviews = Review::where('material_version_id', $review->material_version_id)
            ->with('user:id,name')
            ->get(['id', 'reviewer_id', 'status', 'material_version_id']);
        
        return Inertia::render('admin/workspace/index', [
            'review' => $review,
            'allReviews' => $allReviews,
        ]);
    }

    public function reply(Request $request, Review $review, ReviewComment $comment)
    {
        if ($comment->review_id !== $review->id) {
            abort(404);
        }

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $comment->replies()->create([
            'material_version_id' => $review->material_version_id,
            'review_id' => $review->id,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'anchor_type' => $comment->anchor_type, // Inherit anchor from parent
            'status' => 'open', // Replies are generally just part of the conversation
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function toggleStatus(Request $request, Review $review, ReviewComment $comment)
    {
        if ($comment->review_id !== $review->id) {
            abort(404);
        }

        $newStatus = $comment->status->value === 'open' ? 'addressed' : 'open';
        
        $comment->update([
            'status' => $newStatus,
            'addressed_by' => $newStatus === 'addressed' ? auth()->id() : null,
            'addressed_at' => $newStatus === 'addressed' ? now() : null,
        ]);

        return back()->with('success', 'Status komentar berhasil diubah.');
    }
}
