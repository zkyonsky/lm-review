<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : null;

        $query = Training::query()->withCount([
            'subjects',
            'materials',
            'materials as reviewed_materials_count' => fn ($q) => $q->reviewed($periodId),
        ])->with([
            'periods' => fn ($q) => $q->select('review_periods.id', 'review_periods.name', 'review_periods.year', 'review_periods.status'),
        ]);

        if ($periodId) {
            $query->whereHas('periods', fn ($p) => $p->where('review_periods.id', $periodId));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
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
                    })->orWhere('status', $status);
                } elseif (in_array($status, ['Sedang Direviu', 'Sedang Review', 'in_progress'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereHas('materials', fn ($mq) => $mq->reviewed($periodId))
                          ->whereHas('materials', function ($mq) use ($periodId) {
                              $mq->whereNotIn('status', [\App\Enums\MaterialStatus::Reviewed, \App\Enums\MaterialStatus::Completed])
                                 ->whereDoesntHave('assignments', fn ($aq) => $aq->where('status', \App\Enums\AssignmentStatus::Submitted)->when($periodId, fn ($ap) => $ap->where('review_period_id', $periodId)));
                          });
                    })->orWhere('status', $status);
                } elseif (in_array($status, ['Belum Direviu', 'not_started'])) {
                    $subQ->where(function ($q) use ($periodId) {
                        $q->whereDoesntHave('materials', fn ($mq) => $mq->reviewed($periodId));
                    })->orWhere('status', $status);
                } else {
                    $subQ->where('status', $status);
                }
            });
        }

        $trainings = $query->latest()->paginate(10)->withQueryString();

        $periods = \App\Models\ReviewPeriod::select('id', 'name', 'year', 'status')
            ->latest('year')
            ->latest('id')
            ->get();

        return Inertia::render('admin/trainings/index', [
            'trainings' => $trainings,
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

    public function create()
    {
        return Inertia::render('admin/trainings/create', [
            'statuses' => Training::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', Training::STATUSES),
        ]);

        $validated['status'] = $validated['status'] ?? Training::STATUS_SEDANG_REVIEW;
        $validated['created_by'] = auth()->id();

        Training::create($validated);

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil ditambahkan.');
    }

    public function show(Training $training)
    {
        $training->load('subjects');
        return Inertia::render('admin/trainings/show', [
            'training' => $training
        ]);
    }

    public function edit(Training $training)
    {
        return Inertia::render('admin/trainings/edit', [
            'training' => $training,
            'statuses' => Training::STATUSES,
        ]);
    }

    public function update(Request $request, Training $training)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', Training::STATUSES),
        ]);

        $training->update($validated);

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil diperbarui.');
    }

    public function updateStatusBulk(Request $request)
    {
        $validated = $request->validate([
            'training_ids' => 'required|array|min:1',
            'training_ids.*' => 'exists:trainings,id',
            'status' => 'required|string|in:' . implode(',', Training::STATUSES),
        ]);

        $count = Training::whereIn('id', $validated['training_ids'])
            ->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', "Status {$count} pelatihan berhasil diubah menjadi \"{$validated['status']}\".");
    }

    public function destroy(Training $training)
    {
        $training->delete();

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil dihapus.');
    }

    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'training_ids' => 'required|array|min:1',
            'training_ids.*' => 'exists:trainings,id',
        ]);

        $trainings = Training::whereIn('id', $validated['training_ids'])->get();
        $count = 0;
        foreach ($trainings as $training) {
            $training->delete();
            $count++;
        }

        return redirect()->back()->with('success', "{$count} pelatihan berhasil dihapus.");
    }

    public function generateStmk(Training $training, string $type, \App\Services\StmkService $stmkService)
    {
        if (!in_array($type, ['pengembangan', 'reviu'])) {
            abort(404);
        }

        try {
            $result = $stmkService->generateForTraining($training, $type);

            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message']);
            }

            return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menghasilkan STMK: ' . $e->getMessage());
        }
    }

    public function generateStmkBulk(Request $request, \App\Services\StmkService $stmkService)
    {
        $validated = $request->validate([
            'training_ids' => 'required|array|min:1',
            'training_ids.*' => 'exists:trainings,id',
            'type' => 'required|in:pengembangan,reviu',
        ]);

        try {
            $result = $stmkService->generateBulk($validated['training_ids'], $validated['type']);

            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message']);
            }

            return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menghasilkan STMK massal: ' . $e->getMessage());
        }
    }
}
