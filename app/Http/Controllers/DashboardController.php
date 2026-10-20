<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Review;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('admin');
        $isReviewer = $user->hasRole('reviewer');
        
        $stats = [];

        if ($isAdmin) {
            $stats['admin'] = [
                'total_trainings' => Training::count(),
                'total_materials' => Material::whereHas('subject.training')->count(),
                'total_reviewers' => User::role('reviewer')->count(),
                'reviews_submitted' => Review::whereHasTraining()->where('status', 'submitted')->count(),
                'reviews_draft' => Review::whereHasTraining()->where('status', 'draft')->count(),
            ];
        }

        if ($user->hasAnyRole(['developer', 'pengembang'])) {
            $assignedSubjectIds = $user->developerSubjects()
                ->inActivePeriod()
                ->pluck('subjects.id');

            $materialsQuery = Material::whereIn('subject_id', $assignedSubjectIds)
                ->inActivePeriod();

            $totalMaterials = (clone $materialsQuery)->count();
            $reviewedMaterials = (clone $materialsQuery)->reviewed()->count();

            $stats['developer'] = [
                'total_subjects' => $assignedSubjectIds->count(),
                'total_materials' => $totalMaterials,
                'reviewed_materials' => $reviewedMaterials,
            ];
        }

        if ($isReviewer) {
            $reviewerReviewsQuery = Review::where('reviewer_id', $user->id)
                ->whereHasTraining()
                ->inActivePeriod();

            $stats['reviewer'] = [
                'pending_tasks' => (clone $reviewerReviewsQuery)
                    ->where('status', 'draft')
                    ->count(),
                'completed_tasks' => (clone $reviewerReviewsQuery)
                    ->where('status', 'submitted')
                    ->count(),
            ];
        }

        return Inertia::render('dashboard', [
            'stats' => $stats
        ]);
    }
}
