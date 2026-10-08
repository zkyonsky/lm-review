<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewComment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewWorkspaceController extends Controller
{
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
        
        return Inertia::render('admin/workspace/index', [
            'review' => $review
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
