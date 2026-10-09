<?php

namespace App\Http\Controllers\Developer;

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

        $maxOrder = $subject->materials()->max('sort_order') ?? 0;
        $nextOrder = max($maxOrder, $subject->materials()->count()) + 1;

        return Inertia::render('developer/materials/create', [
            'subject' => $subject->load('training'),
            'types' => MaterialType::options(),
            'nextOrder' => $nextOrder,
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

        $maxOrder = $subject->materials()->max('sort_order') ?? 0;
        $nextOrder = max($maxOrder, $subject->materials()->count()) + 1;

        $subject->materials()->create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? $nextOrder,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('developer.subjects.show', $subject)->with('success', 'Materi berhasil ditambahkan.');
    }

    public function reorder(Request $request, Subject $subject)
    {
        $this->checkSubjectAccess($subject);

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
