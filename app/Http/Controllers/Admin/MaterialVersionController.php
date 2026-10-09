<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class MaterialVersionController extends Controller
{
    public function create(Material $material)
    {
        return Inertia::render('admin/versions/create', [
            'material' => $material
        ]);
    }

    public function store(Request $request, Material $material)
    {
        $request->validate([
            'google_drive_id' => 'nullable|string',
            'scorm_file' => 'nullable|file|mimes:zip|max:512000', // max 500MB
            'is_active' => 'nullable|boolean',
        ]);

        // Validate that at least one is provided based on material type
        if (in_array($material->type->value, ['pdf', 'video']) && empty($request->google_drive_id)) {
            return back()->withErrors(['google_drive_id' => 'Google Drive ID / Link harus diisi untuk materi PDF/Video.']);
        }

        if ($material->type->value === 'scorm' && !$request->hasFile('scorm_file')) {
            return back()->withErrors(['scorm_file' => 'File SCORM (ZIP) harus diunggah.']);
        }

        // Determine new version number
        $latestVersion = $material->versions()->max('version_number') ?? 0;
        $newVersionNumber = $latestVersion + 1;

        $versionData = [
            'version_number' => $newVersionNumber,
            'created_by' => auth()->id(),
        ];

        // Process PDF/Video
        if (in_array($material->type->value, ['pdf', 'video'])) {
            // Extract ID if full link is provided
            $driveId = $request->google_drive_id;
            if (preg_match('/d\/([a-zA-Z0-9_-]+)/', $driveId, $matches)) {
                $driveId = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $driveId, $matches)) {
                $driveId = $matches[1];
            }
            $versionData['gdrive_file_id'] = $driveId;
            $versionData['gdrive_url'] = $request->google_drive_id;
        }

        // Process SCORM
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

        // Automatically assign reviewers who are assigned to the subject
        $subjectReviewers = $material->subject->reviewers;
        foreach ($subjectReviewers as $reviewer) {
            $assignment = \App\Models\ReviewAssignment::firstOrCreate([
                'material_id' => $material->id,
                'reviewer_id' => $reviewer->id,
            ], [
                'assigned_by' => auth()->id(),
                'status' => 'pending',
            ]);

            $review = \App\Models\Review::firstOrCreate([
                'material_version_id' => $version->id,
                'reviewer_id' => $reviewer->id,
            ], [
                'review_assignment_id' => $assignment->id,
                'status' => 'draft',
            ]);

            \App\Facades\WhatsApp::notifyNewVersionUploaded($reviewer, $version, $review);
        }

        if ($material->type->value === 'scorm') {
            // Process SCORM package synchronously so it is immediately available
            \App\Jobs\ProcessScormPackage::dispatchSync($version);
            return redirect()->route('admin.materials.show', $material)->with('success', 'Versi materi berhasil diunggah dan siap direviu.');
        }

        return redirect()->route('admin.materials.show', $material)->with('success', 'Versi materi berhasil ditambahkan.');
    }
    
    public function show(MaterialVersion $version)
    {
        $version->load(['material.subject.training', 'scormPackage.scos', 'reviews.user']);
        
        $availableReviewers = \App\Models\User::role('reviewer')->get(['id', 'name']);

        return Inertia::render('admin/versions/show', [
            'version' => $version,
            'availableReviewers' => $availableReviewers
        ]);
    }
    
    public function toggleActive(MaterialVersion $version)
    {
        $material = $version->material;
        
        $newCurrentId = $material->current_version_id === $version->id ? null : $version->id;
        $material->update(['current_version_id' => $newCurrentId]);
        
        return back()->with('success', 'Versi aktif berhasil diubah.');
    }
    
    public function destroy(MaterialVersion $version)
    {
        $materialId = $version->material_id;
        
        if ($version->file_path) {
            Storage::disk('local')->delete($version->file_path);
            // Also delete extracted folder
            Storage::disk('public')->deleteDirectory('scorm/' . $version->id);
        }
        
        $version->delete();
        
        return redirect()->route('admin.materials.show', $materialId)->with('success', 'Versi materi berhasil dihapus.');
    }
}
