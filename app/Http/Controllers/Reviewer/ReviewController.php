<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Enums\AssignmentStatus;
use App\Enums\CommentAnchor;
use App\Enums\CommentStatus;
use App\Enums\ReviewStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Enum;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::where('reviewer_id', auth()->id())
            ->with(['materialVersion.material.subject.training'])
            ->latest()
            ->paginate(10);
            
        return Inertia::render('reviewer/reviews/index', [
            'reviews' => $reviews
        ]);
    }

    public function workspace(Review $review)
    {
        if ($review->reviewer_id !== auth()->id()) {
            abort(403);
        }

        $review->load([
            'materialVersion.material.subject.training', 
            'materialVersion.scormPackage.scos',
            'comments' => function ($query) {
                $query->root()->with(['replies.user', 'user', 'sco']);
            }
        ]);
        
        return Inertia::render('reviewer/workspace/index', [
            'review' => $review
        ]);
    }

    public function storeComment(Request $request, Review $review)
    {
        if ($review->reviewer_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'required|string',
            'anchor_type' => ['nullable', new Enum(CommentAnchor::class)],
            'page_number' => 'nullable|integer',
            'timestamp_seconds' => 'nullable|integer',
            'scorm_sco_id' => 'nullable|exists:scorm_scos,id',
            'parent_id' => 'nullable|exists:review_comments,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $parent = null;

        if ($parentId) {
            $parent = ReviewComment::findOrFail($parentId);
            if ($parent->review_id !== $review->id) {
                abort(404);
            }
        } else {
            $isDraft = $review->status === ReviewStatus::Draft || $review->status === ReviewStatus::Draft->value;
            if (!$isDraft) {
                return back()->with('error', 'Review sudah disubmit. Anda hanya dapat membalas komentar.');
            }
        }

        $anchorType = $validated['anchor_type'] 
            ?? ($parent?->anchor_type?->value ?? ($parent?->anchor_type ?? CommentAnchor::General->value));

        $review->comments()->create([
            'material_version_id' => $review->material_version_id,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'anchor_type' => $anchorType,
            'page_number' => $validated['page_number'] ?? $parent?->page_number,
            'timestamp_seconds' => $validated['timestamp_seconds'] ?? $parent?->timestamp_seconds,
            'scorm_sco_id' => $validated['scorm_sco_id'] ?? $parent?->scorm_sco_id,
            'parent_id' => $parentId,
            'status' => CommentStatus::Open->value,
        ]);

        return back()->with('success', $parentId ? 'Balasan berhasil dikirim.' : 'Komentar berhasil ditambahkan.');
    }

    public function submitReview(Request $request, Review $review)
    {
        if ($review->reviewer_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'general_notes' => 'nullable|string',
        ]);

        $review->update([
            'status' => ReviewStatus::Submitted->value,
            'general_notes' => $validated['general_notes'] ?? $review->general_notes,
            'submitted_at' => now(),
        ]);

        $review->assignment?->update([
            'status' => AssignmentStatus::Submitted->value,
        ]);

        return back()->with('success', 'Review berhasil disubmit. Anda tidak bisa lagi menambahkan komentar utama, namun masih bisa membalas percakapan.');
    }
}
