<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;

class ReviewAssignmentController extends Controller
{
    public function store(Request $request, MaterialVersion $version)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        
        if (!$user->hasRole('reviewer')) {
            return back()->with('error', 'Pengguna yang dipilih bukan seorang Reviewer.');
        }

        // Prevent duplicate assignments
        if ($version->reviews()->where('reviewer_id', $user->id)->exists()) {
            return back()->with('error', 'Reviewer sudah ditugaskan pada versi ini.');
        }

        $assignment = \App\Models\ReviewAssignment::firstOrCreate([
            'material_id' => $version->material_id,
            'reviewer_id' => $user->id,
        ], [
            'assigned_by' => auth()->id(),
            'status' => 'pending',
        ]);

        $version->reviews()->create([
            'review_assignment_id' => $assignment->id,
            'reviewer_id' => $user->id,
            'status' => 'draft',
        ]);

        return back()->with('success', 'Reviewer berhasil ditugaskan.');
    }

    public function destroy(MaterialVersion $version, Review $review)
    {
        if ($review->material_version_id !== $version->id) {
            abort(404);
        }

        $review->delete();

        return back()->with('success', 'Penugasan reviewer berhasil dihapus.');
    }
}
