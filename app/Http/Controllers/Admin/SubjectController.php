<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function create(Training $training)
    {
        $maxOrder = $training->subjects()->max('sort_order') ?? 0;
        $nextOrder = max($maxOrder, $training->subjects()->count()) + 1;

        return Inertia::render('admin/subjects/create', [
            'training' => $training,
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request, Training $training)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
        ]);

        $maxOrder = $training->subjects()->max('sort_order') ?? 0;
        $nextOrder = max($maxOrder, $training->subjects()->count()) + 1;

        $training->subjects()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? $nextOrder,
        ]);

        return redirect()->route('admin.trainings.show', $training)->with('success', 'Mata pelatihan berhasil ditambahkan.');
    }

    public function reorder(Request $request, Training $training)
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer',
            'orders.*.order' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($training, $validated) {
            foreach ($validated['orders'] as $item) {
                $training->subjects()->where('id', $item['id'])->update([
                    'sort_order' => $item['order'],
                ]);
            }
        });

        return back()->with('success', 'Urutan mata pelatihan berhasil diperbarui.');
    }

    public function show(Subject $subject)
    {
        $subject->load([
            'training',
            'materials.versions',
            'developers',
            'reviewers',
        ]);

        $availableDevelopers = User::role('pengembang')
            ->where('is_active', true)
            ->get(['id', 'name', 'email', 'nip', 'unit_kerja']);

        $availableReviewers = User::role('reviewer')
            ->where('is_active', true)
            ->get(['id', 'name', 'email', 'nip', 'unit_kerja']);

        return Inertia::render('admin/subjects/show', [
            'subject' => $subject,
            'availableDevelopers' => $availableDevelopers,
            'availableReviewers' => $availableReviewers,
        ]);
    }

    public function edit(Subject $subject)
    {
        $subject->load('training');
        return Inertia::render('admin/subjects/edit', [
            'subject' => $subject
        ]);
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
        ]);

        $subject->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? $subject->sort_order,
        ]);

        return redirect()->route('admin.trainings.show', $subject->training_id)->with('success', 'Mata pelatihan berhasil diperbarui.');
    }

    public function destroy(Subject $subject)
    {
        $trainingId = $subject->training_id;
        $subject->delete();

        return redirect()->route('admin.trainings.show', $trainingId)->with('success', 'Mata pelatihan berhasil dihapus.');
    }
}
