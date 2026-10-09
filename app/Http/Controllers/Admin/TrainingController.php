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
        $query = Training::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $trainings = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('admin/trainings/index', [
            'trainings' => $trainings,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
            ],
            'statuses' => Training::STATUSES,
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
            'code' => 'required|string|max:50|unique:trainings,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
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
            'code' => 'required|string|max:50|unique:trainings,code,' . $training->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
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
