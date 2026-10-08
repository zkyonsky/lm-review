<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;

class SubjectAssignmentController extends Controller
{
    public function store(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:pengembang,reviewer',
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($validated['role'] === 'pengembang') {
            if (!$user->hasRole('pengembang')) {
                return back()->with('error', 'Pengguna yang dipilih bukan seorang Pengembang Materi.');
            }
        } elseif ($validated['role'] === 'reviewer') {
            if (!$user->hasRole('reviewer')) {
                return back()->with('error', 'Pengguna yang dipilih bukan seorang Reviewer.');
            }
        }

        // Prevent duplicate assignment for same subject and role
        $exists = $subject->assignedUsers()
            ->where('users.id', $user->id)
            ->wherePivot('role', $validated['role'])
            ->exists();

        if ($exists) {
            $roleLabel = $validated['role'] === 'pengembang' ? 'Pengembang Materi' : 'Reviewer';
            return back()->with('error', "{$user->name} sudah ditugaskan sebagai {$roleLabel} pada mata pelatihan ini.");
        }

        $subject->assignedUsers()->attach($user->id, [
            'role' => $validated['role'],
            'assigned_by' => auth()->id(),
        ]);

        if ($validated['role'] === 'reviewer') {
            // Automatically assign reviewer to all materials with versions under this subject
            foreach ($subject->materials as $material) {
                $version = $material->currentVersion ?? $material->versions()->latest()->first();
                if ($version) {
                    $assignment = \App\Models\ReviewAssignment::firstOrCreate([
                        'material_id' => $material->id,
                        'reviewer_id' => $user->id,
                    ], [
                        'assigned_by' => auth()->id(),
                        'status' => 'pending',
                    ]);

                    \App\Models\Review::firstOrCreate([
                        'material_version_id' => $version->id,
                        'reviewer_id' => $user->id,
                    ], [
                        'review_assignment_id' => $assignment->id,
                        'status' => 'draft',
                    ]);
                }
            }
        }

        $roleLabel = $validated['role'] === 'pengembang' ? 'Pengembang Materi' : 'Reviewer';
        return back()->with('success', "{$user->name} berhasil ditugaskan sebagai {$roleLabel}.");
    }

    public function destroy(Request $request, Subject $subject, User $user)
    {
        $role = $request->input('role') ?? $request->query('role');

        if ($role) {
            $subject->assignedUsers()->wherePivot('role', $role)->detach($user->id);
        } else {
            $subject->assignedUsers()->detach($user->id);
        }

        if ($role === 'reviewer' || !$role) {
            $materialIds = $subject->materials()->pluck('id');
            $assignments = \App\Models\ReviewAssignment::whereIn('material_id', $materialIds)
                ->where('reviewer_id', $user->id)
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->reviews()->where('status', 'draft')->delete();
                if ($assignment->reviews()->count() === 0) {
                    $assignment->delete();
                }
            }
        }

        return back()->with('success', 'Penugasan berhasil dicabut.');
    }
}
