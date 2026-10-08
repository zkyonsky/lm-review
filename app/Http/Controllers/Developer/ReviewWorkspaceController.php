<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewComment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewWorkspaceController extends Controller
{
    protected function checkSubjectAccess(Review $review): void
    {
        $user = auth()->user();
        $subject = $review->materialVersion->material->subject;
        $isAssigned = $subject->developers()->where('users.id', $user->id)->exists();
        if (!$isAssigned && !$user->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki akses ke ulasan ini.');
        }
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

        $this->checkSubjectAccess($review);
        
        return Inertia::render('developer/workspace/index', [
            'review' => $review
        ]);
    }

    public function reply(Request $request, Review $review, ReviewComment $comment)
    {
        if ($comment->review_id !== $review->id) {
            abort(404);
        }

        $this->checkSubjectAccess($review);

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $comment->replies()->create([
            'material_version_id' => $review->material_version_id,
            'review_id' => $review->id,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'anchor_type' => $comment->anchor_type,
            'status' => 'open',
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    public function toggleStatus(Request $request, Review $review, ReviewComment $comment)
    {
        if ($comment->review_id !== $review->id) {
            abort(404);
        }

        $this->checkSubjectAccess($review);

        $newStatus = $comment->status->value === 'open' ? 'addressed' : 'open';
        
        $comment->update([
            'status' => $newStatus,
            'addressed_by' => $newStatus === 'addressed' ? auth()->id() : null,
            'addressed_at' => $newStatus === 'addressed' ? now() : null,
        ]);

        return back()->with('success', 'Status komentar berhasil diubah.');
    }
}
