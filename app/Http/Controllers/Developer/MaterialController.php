<?php

namespace App\Http\Controllers\Developer;

use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Enum;

class MaterialController extends Controller
{
    protected function checkSubjectAccess(Subject $subject): void
    {
        $user = auth()->user();
        $isAssigned = $subject->developers()->where('users.id', $user->id)->exists();
        if (!$isAssigned && !$user->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki akses ke mata pelatihan ini.');
        }
    }

    public function create(Subject $subject)
    {
        $this->checkSubjectAccess($subject);

        return Inertia::render('developer/materials/create', [
            'subject' => $subject->load('training'),
            'types' => MaterialType::options(),
        ]);
    }

    public function store(Request $request, Subject $subject)
    {
        $this->checkSubjectAccess($subject);

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

        return redirect()->route('developer.subjects.show', $subject)->with('success', 'Materi berhasil ditambahkan.');
    }

    public function show(Material $material)
    {
        $this->checkSubjectAccess($material->subject);

        $material->load([
            'subject.training',
            'versions.reviews.user',
            'versions.scormPackage.scos',
        ]);

        return Inertia::render('developer/materials/show', [
            'material' => $material,
        ]);
    }

    public function edit(Material $material)
    {
        $this->checkSubjectAccess($material->subject);

        $material->load('subject.training');

        return Inertia::render('developer/materials/edit', [
            'material' => $material,
            'types' => MaterialType::options(),
        ]);
    }

    public function update(Request $request, Material $material)
    {
        $this->checkSubjectAccess($material->subject);

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

        return redirect()->route('developer.subjects.show', $material->subject_id)->with('success', 'Materi berhasil diperbarui.');
    }
}
