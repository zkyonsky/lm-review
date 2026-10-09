<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Enum;

class MaterialController extends Controller
{
    public function create(Subject $subject)
    {
        $maxOrder = $subject->materials()->max('sort_order') ?? 0;
        $nextOrder = max($maxOrder, $subject->materials()->count()) + 1;

        return Inertia::render('admin/materials/create', [
            'subject' => $subject,
            'types' => MaterialType::options(),
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', new Enum(MaterialType::class)],
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
        ]);

        $maxOrder = $subject->materials()->max('sort_order') ?? 0;
        $nextOrder = max($maxOrder, $subject->materials()->count()) + 1;

        $subject->materials()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? $nextOrder,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.subjects.show', $subject)->with('success', 'Materi berhasil ditambahkan.');
    }

    public function reorder(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer',
            'orders.*.order' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($subject, $validated) {
            foreach ($validated['orders'] as $item) {
                $subject->materials()->where('id', $item['id'])->update([
                    'sort_order' => $item['order'],
                ]);
            }
        });

        return back()->with('success', 'Urutan materi berhasil diperbarui.');
    }

    public function show(Material $material)
    {
        $material->load(['subject.training', 'versions.reviews.user']);
        return Inertia::render('admin/materials/show', [
            'material' => $material
        ]);
    }

    public function edit(Material $material)
    {
        $material->load('subject');
        return Inertia::render('admin/materials/edit', [
            'material' => $material,
            'types' => MaterialType::options(),
        ]);
    }

    public function update(Request $request, Material $material)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', new Enum(MaterialType::class)],
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
        ]);

        $material->update([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? $material->sort_order,
        ]);

        return redirect()->route('admin.subjects.show', $material->subject_id)->with('success', 'Materi berhasil diperbarui.');
    }

    public function destroy(Material $material)
    {
        $subjectId = $material->subject_id;
        $material->delete();

        return redirect()->route('admin.subjects.show', $subjectId)->with('success', 'Materi berhasil dihapus.');
    }
}
