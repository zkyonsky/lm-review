<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingController extends Controller
{
    public function index()
    {
        $trainings = Training::latest()->paginate(10);
        return Inertia::render('admin/trainings/index', [
            'trainings' => $trainings
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/trainings/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:trainings,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

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
            'training' => $training
        ]);
    }

    public function update(Request $request, Training $training)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:trainings,code,' . $training->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $training->update($validated);

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil diperbarui.');
    }

    public function destroy(Training $training)
    {
        $training->delete();

        return redirect()->route('admin.trainings.index')->with('success', 'Pelatihan berhasil dihapus.');
    }
}
