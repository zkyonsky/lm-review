<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Enum;

class MaterialController extends Controller
{
    public function create(Subject $subject)
    {
        return Inertia::render('admin/materials/create', [
            'subject' => $subject,
            'types' => MaterialType::options(),
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

        $subject->materials()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.subjects.show', $subject)->with('success', 'Materi berhasil ditambahkan.');
    }

    public function show(Material $material)
    {
        $material->load(['subject.training', 'versions.reviews']);
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
