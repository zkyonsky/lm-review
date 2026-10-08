<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $subjects = $user->developerSubjects()
            ->with(['training', 'reviewers:id,name,email,unit_kerja', 'materials.versions'])
            ->withCount('materials')
            ->get();

        return Inertia::render('developer/subjects/index', [
            'subjects' => $subjects,
        ]);
    }

    public function show(Subject $subject)
    {
        $user = auth()->user();

        // Ensure user is assigned as developer to this subject
        $isAssigned = $subject->developers()->where('users.id', $user->id)->exists();
        if (!$isAssigned && !$user->hasRole('admin')) {
            abort(403, 'Anda tidak ditugaskan pada mata pelatihan ini.');
        }

        $subject->load([
            'training',
            'reviewers:id,name,email,nip,unit_kerja',
            'developers:id,name,email,nip,unit_kerja',
            'materials' => function ($query) {
                $query->with(['currentVersion', 'versions.reviews.user'])
                    ->orderBy('sort_order');
            }
        ]);

        return Inertia::render('developer/subjects/show', [
            'subject' => $subject,
        ]);
    }
}
