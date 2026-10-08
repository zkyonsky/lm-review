<?php

namespace App\Http\Controllers;

use App\Models\MaterialVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function show(Request $request, MaterialVersion $version)
    {
        $version->load(['material.subject.training', 'scormPackage.scos']);
        
        $user = auth()->user();
        if (!$version->is_active && (!$user || !$user->hasAnyRole(['admin', 'developer', 'pengembang', 'reviewer']))) {
            abort(403, 'Versi materi ini tidak aktif.');
        }

        $isEmbedded = $request->boolean('embed');

        if (in_array($version->material->type->value, ['pdf', 'video'])) {
            return Inertia::render('viewer/drive', [
                'version' => $version,
                'isEmbedded' => $isEmbedded,
            ]);
        }

        if ($version->material->type->value === 'scorm') {
            if (!$version->scormPackage || $version->scormPackage->status !== 'ready') {
                // Try processing synchronously on the fly
                \App\Jobs\ProcessScormPackage::dispatchSync($version);
                $version->refresh()->load('scormPackage.scos');
            }

            if (!$version->scormPackage || $version->scormPackage->status !== 'ready') {
                abort(404, 'Paket SCORM belum siap: ' . ($version->scormPackage?->error_message ?? 'Sedang diproses.'));
            }

            return Inertia::render('viewer/scorm', [
                'version' => $version,
                'isEmbedded' => $isEmbedded,
            ]);
        }

        abort(404);
    }
}
