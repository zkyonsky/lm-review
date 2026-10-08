<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaterialVersionController extends Controller
{
    protected function checkSubjectAccess(Material $material): void
    {
        $user = auth()->user();
        $isAssigned = $material->subject->developers()->where('users.id', $user->id)->exists();
        if (!$isAssigned && !$user->hasRole('admin')) {
            abort(403, 'Anda tidak memiliki akses ke materi ini.');
        }
    }

    public function create(Material $material)
    {
        $this->checkSubjectAccess($material);

        return Inertia::render('developer/versions/create', [
            'material' => $material->load('subject.training'),
        ]);
    }

    public function store(Request $request, Material $material)
    {
        $this->checkSubjectAccess($material);

        $request->validate([
            'google_drive_id' => 'nullable|string',
            'scorm_file' => 'nullable|file|mimes:zip|max:512000', // max 500MB
            'is_active' => 'nullable|boolean',
        ]);

        if (in_array($material->type->value, ['pdf', 'video']) && empty($request->google_drive_id)) {
            return back()->withErrors(['google_drive_id' => 'Google Drive ID / Link harus diisi untuk materi PDF/Video.']);
        }

        if ($material->type->value === 'scorm' && !$request->hasFile('scorm_file')) {
            return back()->withErrors(['scorm_file' => 'File SCORM (ZIP) harus diunggah.']);
        }

        $latestVersion = $material->versions()->max('version_number') ?? 0;
        $newVersionNumber = $latestVersion + 1;

        $versionData = [
            'version_number' => $newVersionNumber,
            'created_by' => auth()->id(),
        ];

        if (in_array($material->type->value, ['pdf', 'video'])) {
            $driveId = $request->google_drive_id;
            if (preg_match('/d\/([a-zA-Z0-9_-]+)/', $driveId, $matches)) {
                $driveId = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $driveId, $matches)) {
                $driveId = $matches[1];
            }
            $versionData['gdrive_file_id'] = $driveId;
            $versionData['gdrive_url'] = $request->google_drive_id;
        }

        if ($material->type->value === 'scorm' && $request->hasFile('scorm_file')) {
            $file = $request->file('scorm_file');
            $path = $file->store('scorm_uploads', 'local');
            $versionData['file_path'] = $path;
            $versionData['original_filename'] = $file->getClientOriginalName();
            $versionData['file_size'] = $file->getSize();
        }

        $version = $material->versions()->create($versionData);

        $isActive = $request->boolean('is_active', true);
        if ($isActive || is_null($material->current_version_id)) {
            $material->update(['current_version_id' => $version->id]);
        }

        // Automatically create Review for reviewers assigned to the material's subject
        $subjectReviewers = $material->subject->reviewers;
        foreach ($subjectReviewers as $reviewer) {
            $assignment = \App\Models\ReviewAssignment::firstOrCreate([
                'material_id' => $material->id,
                'reviewer_id' => $reviewer->id,
            ], [
                'assigned_by' => auth()->id(),
                'status' => 'pending',
            ]);

            \App\Models\Review::firstOrCreate([
                'material_version_id' => $version->id,
                'reviewer_id' => $reviewer->id,
            ], [
                'review_assignment_id' => $assignment->id,
                'status' => 'draft',
            ]);
        }

        if ($material->type->value === 'scorm') {
            \App\Jobs\ProcessScormPackage::dispatchSync($version);
            return redirect()->route('developer.materials.show', $material)->with('success', 'Versi materi berhasil diunggah dan siap direviu.');
        }

        return redirect()->route('developer.materials.show', $material)->with('success', 'Versi materi baru berhasil ditambahkan.');
    }

    public function show(MaterialVersion $version)
    {
        $version->load(['material.subject.training', 'scormPackage.scos', 'reviews.user']);
        $this->checkSubjectAccess($version->material);

        return Inertia::render('developer/versions/show', [
            'version' => $version,
        ]);
    }
}
