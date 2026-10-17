<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentStatus;
use App\Enums\MaterialStatus;
use App\Enums\ReviewPeriodStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewPeriod;
use App\Models\Training;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewPeriodController extends Controller
{
    public function index(Request $request)
    {
        $query = ReviewPeriod::with('creator:id,name')
            ->withCount([
                'trainings',
                'assignments',
                'assignments as submitted_assignments_count' => fn ($q) => $q->where('status', AssignmentStatus::Submitted),
                'assignments as pending_assignments_count' => fn ($q) => $q->where('status', '!=', AssignmentStatus::Submitted),
            ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('year')) {
            $query->where('year', (int) $request->input('year'));
        }

        $periods = $query->latest('year')->latest('id')->paginate(10)->withQueryString();

        $allTrainings = Training::select('id', 'title', 'status', 'year')
            ->withCount('materials')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/periods/index', [
            'periods' => $periods,
            'allTrainings' => $allTrainings,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'year' => $request->input('year', ''),
            ],
            'statuses' => ReviewPeriodStatus::options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|integer|min:2020|max:2099',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|string|in:' . implode(',', array_column(ReviewPeriodStatus::cases(), 'value')),
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();

        ReviewPeriod::create($validated);

        return redirect()->route('admin.periods.index')->with('success', 'Periode reviu berhasil dibuat.');
    }

    public function update(Request $request, ReviewPeriod $period)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|integer|min:2020|max:2099',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|string|in:' . implode(',', array_column(ReviewPeriodStatus::cases(), 'value')),
            'description' => 'nullable|string',
        ]);

        $period->update($validated);

        return redirect()->back()->with('success', 'Periode reviu berhasil diperbarui.');
    }

    public function destroy(ReviewPeriod $period)
    {
        $period->delete();

        return redirect()->route('admin.periods.index')->with('success', 'Periode reviu berhasil dihapus.');
    }

    public function show(Request $request, ReviewPeriod $period)
    {
        $period->load('creator:id,name');
        $period->loadCount([
            'trainings',
            'assignments',
            'assignments as submitted_assignments_count' => fn ($q) => $q->where('status', AssignmentStatus::Submitted),
            'assignments as pending_assignments_count' => fn ($q) => $q->where('status', '!=', AssignmentStatus::Submitted),
        ]);

        $periodId = $period->id;

        $query = $period->trainings()->withCount([
            'subjects',
            'materials',
            'materials as reviewed_materials_count' => fn ($q) => $q->reviewed($periodId),
        ])->with(['subjects.reviewers:id,name']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('trainings.title', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where(function ($subQ) use ($status, $periodId) {
                if (in_array($status, ['Selesai Reviu', 'Selesai Review', 'completed'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereHas('materials')
                          ->whereDoesntHave('materials', function ($mq) use ($periodId) {
                              $mq->whereNotIn('status', [MaterialStatus::Reviewed, MaterialStatus::Completed])
                                 ->whereDoesntHave('assignments', fn ($aq) => $aq->where('status', AssignmentStatus::Submitted)->where('review_period_id', $periodId));
                          });
                    });
                } elseif (in_array($status, ['Sedang Direviu', 'Sedang Review', 'in_progress'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereHas('materials', fn ($mq) => $mq->reviewed($periodId))
                          ->whereHas('materials', function ($mq) use ($periodId) {
                              $mq->whereNotIn('status', [MaterialStatus::Reviewed, MaterialStatus::Completed])
                                 ->whereDoesntHave('assignments', fn ($aq) => $aq->where('status', AssignmentStatus::Submitted)->where('review_period_id', $periodId));
                          });
                    });
                } elseif (in_array($status, ['Belum Direviu', 'not_started'])) {
                    $subQ->whereDoesntHave('materials', fn ($mq) => $mq->reviewed($periodId));
                }
            });
        }

        $trainings = $query->paginate(10)->withQueryString();

        // Ambil pelatihan master yang belum terdaftar di periode ini untuk modal
        $enrolledIds = $period->trainings()->pluck('trainings.id');
        $availableTrainings = Training::select('id', 'title', 'year', 'status')
            ->whereNotIn('id', $enrolledIds)
            ->withCount('materials')
            ->orderBy('title')
            ->get();

        return Inertia::render('admin/periods/show', [
            'period' => $period,
            'trainings' => $trainings,
            'availableTrainings' => $availableTrainings,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
            'statuses' => ['Selesai Reviu', 'Sedang Direviu', 'Belum Direviu'],
        ]);
    }

    public function removeTraining(ReviewPeriod $period, Training $training)
    {
        $period->trainings()->detach($training->id);

        $materialIds = $training->materials()->pluck('materials.id');
        ReviewAssignment::where('review_period_id', $period->id)
            ->whereIn('material_id', $materialIds)
            ->where('status', '!=', AssignmentStatus::Submitted)
            ->delete();

        return redirect()->back()->with(
            'success',
            "Pelatihan \"{$training->title}\" berhasil dikeluarkan dari periode \"{$period->name}\"."
        );
    }

    public function enrollTrainings(Request $request, ReviewPeriod $period)
    {
        $validated = $request->validate([
            'training_ids' => 'required|array|min:1',
            'training_ids.*' => 'exists:trainings,id',
        ]);

        $trainingIds = $validated['training_ids'];
        $period->trainings()->syncWithoutDetaching($trainingIds);

        $trainings = Training::with(['subjects.materials.currentVersion', 'subjects.reviewers'])->whereIn('id', $trainingIds)->get();

        $enrolledMaterialsCount = 0;
        $assignmentsCreatedCount = 0;

        foreach ($trainings as $training) {
            foreach ($training->subjects as $subject) {
                $reviewers = $subject->reviewers;
                foreach ($subject->materials as $material) {
                    $enrolledMaterialsCount++;

                    // Set status to in_review for periodic evaluation
                    $material->update(['status' => MaterialStatus::InReview]);

                    // Assign to all reviewers attached to the subject
                    foreach ($reviewers as $reviewer) {
                        $assignment = ReviewAssignment::firstOrCreate([
                            'review_period_id' => $period->id,
                            'material_id' => $material->id,
                            'reviewer_id' => $reviewer->id,
                        ], [
                            'assigned_by' => auth()->id(),
                            'status' => AssignmentStatus::Pending,
                            'due_date' => $period->end_date,
                        ]);

                        if ($assignment->wasRecentlyCreated) {
                            $assignmentsCreatedCount++;
                        }

                        $version = $material->currentVersion ?? $material->versions()->latest('version_number')->first();
                        if ($version) {
                            Review::firstOrCreate([
                                'review_assignment_id' => $assignment->id,
                                'material_version_id' => $version->id,
                            ], [
                                'reviewer_id' => $reviewer->id,
                                'status' => ReviewStatus::Draft,
                            ]);
                        }
                    }
                }
            }
        }

        return redirect()->back()->with(
            'success',
            count($trainingIds) . " pelatihan berhasil diikutsertakan ke periode \"{$period->name}\". {$enrolledMaterialsCount} materi disiapkan untuk reviu ulang."
        );
    }
}
