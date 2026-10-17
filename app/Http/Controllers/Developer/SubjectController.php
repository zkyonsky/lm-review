<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Training;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : null;

        $query = $user->developerSubjects()
            ->whereHas('training')
            ->with(['training', 'reviewers:id,name,email,unit_kerja', 'materials.versions'])
            ->withCount([
                'materials',
                'materials as reviewed_materials_count' => fn ($q) => $q->reviewed($periodId),
            ]);

        if ($periodId) {
            $query->whereHas('training.periods', fn ($p) => $p->where('review_periods.id', $periodId));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subjects.title', 'like', "%{$search}%")
                    ->orWhereHas('training', function ($t) use ($search) {
                        $t->where('title', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('training_name')) {
            $trainingName = $request->input('training_name');
            $query->whereHas('training', function ($t) use ($trainingName) {
                $t->where('title', 'like', "%{$trainingName}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where(function ($subQ) use ($status, $periodId) {
                if (in_array($status, ['Selesai Reviu', 'Selesai Review', 'completed'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereHas('materials')
                          ->whereDoesntHave('materials', function ($mq) use ($periodId) {
                              $mq->whereNotIn('status', [\App\Enums\MaterialStatus::Reviewed, \App\Enums\MaterialStatus::Completed])
                                 ->whereDoesntHave('assignments', fn ($aq) => $aq->where('status', \App\Enums\AssignmentStatus::Submitted)->when($periodId, fn ($ap) => $ap->where('review_period_id', $periodId)));
                          });
                    })->orWhereHas('training', fn ($t) => $t->where('status', $status));
                } elseif (in_array($status, ['Sedang Direviu', 'Sedang Review', 'in_progress'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereHas('materials', fn ($mq) => $mq->reviewed($periodId))
                          ->whereHas('materials', function ($mq) use ($periodId) {
                              $mq->whereNotIn('status', [\App\Enums\MaterialStatus::Reviewed, \App\Enums\MaterialStatus::Completed])
                                 ->whereDoesntHave('assignments', fn ($aq) => $aq->where('status', \App\Enums\AssignmentStatus::Submitted)->when($periodId, fn ($ap) => $ap->where('review_period_id', $periodId)));
                          });
                    })->orWhereHas('training', fn ($t) => $t->where('status', $status));
                } elseif (in_array($status, ['Belum Direviu', 'not_started'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereDoesntHave('materials', fn ($mq) => $mq->reviewed($periodId));
                    })->orWhereHas('training', fn ($t) => $t->where('status', $status));
                } else {
                    $subQ->whereHas('training', fn ($t) => $t->where('status', $status));
                }
            });
        }

        $subjects = $query->latest('subjects.id')->paginate(10)->withQueryString();

        $periods = \App\Models\ReviewPeriod::select('id', 'name', 'year', 'status')
            ->latest('year')
            ->latest('id')
            ->get();

        return Inertia::render('developer/subjects/index', [
            'subjects' => $subjects,
            'periods' => $periods,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'period_id' => $request->input('period_id', ''),
            ],
            'statuses' => [
                'Selesai Reviu',
                'Sedang Direviu',
                'Belum Direviu',
            ],
        ]);
    }

    public function updateStatusBulk(Request $request)
    {
        $validated = $request->validate([
            'subject_ids' => 'required_without:training_ids|array',
            'subject_ids.*' => 'integer',
            'training_ids' => 'required_without:subject_ids|array',
            'training_ids.*' => 'integer',
            'status' => 'required|string|in:' . implode(',', Training::STATUSES),
        ]);

        $user = auth()->user();

        if ($user->hasRole('admin')) {
            $trainingQuery = Training::query();
            if ($request->filled('training_ids')) {
                $trainingQuery->whereIn('id', $request->input('training_ids'));
            } elseif ($request->filled('subject_ids')) {
                $trainingQuery->whereHas('subjects', fn($q) => $q->whereIn('id', $request->input('subject_ids')));
            }
            $trainingIds = $trainingQuery->pluck('id')->all();
        } else {
            $query = $user->developerSubjects()->whereHas('training');

            if ($request->filled('subject_ids')) {
                $query->whereIn('subjects.id', $request->input('subject_ids'));
            }

            if ($request->filled('training_ids')) {
                $query->whereIn('subjects.training_id', $request->input('training_ids'));
            }

            $trainingIds = $query->pluck('subjects.training_id')
                ->unique()
                ->values()
                ->all();
        }

        if (empty($trainingIds)) {
            return back()->with('error', 'Tidak ada pelatihan valid yang dapat diperbarui.');
        }

        Training::whereIn('id', $trainingIds)->update([
            'status' => $validated['status'],
        ]);

        $count = count($trainingIds);
        return back()->with('success', "Status {$count} pelatihan berhasil diperbarui menjadi '{$validated['status']}'.");
    }

    public function show(Subject $subject)
    {
        $user = auth()->user();

        if (!$subject->training) {
            return redirect()->route('developer.subjects.index')->with('error', 'Pelatihan terkait telah dihapus atau tidak ditemukan.');
        }

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
