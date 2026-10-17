<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\GeminiAnalysisService;
use Illuminate\Http\Request;

class AiAnalysisController extends Controller
{
    public function analyze(Request $request, Review $review, GeminiAnalysisService $geminiService)
    {
        $user = auth()->user();

        // Cek izin akses: Reviewer yang ditugaskan, Pengembang yang ditugaskan, atau Admin
        $isReviewer = $review->reviewer_id === $user->id;
        $isDeveloper = $review->materialVersion?->material?->subject?->developers()
            ->where('users.id', $user->id)
            ->exists();
        $isAdmin = $user->hasRole('admin');

        if (!$isReviewer && !$isDeveloper && !$isAdmin) {
            abort(403, 'Anda tidak memiliki wewenang untuk menganalisis materi pada ulasan ini.');
        }

        $validated = $request->validate([
            'custom_context' => 'nullable|string|max:5000',
        ]);

        $customContext = $validated['custom_context'] ?? null;

        $analysis = $geminiService->analyzeMaterialVersion(
            $review->materialVersion,
            $user,
            $customContext
        );

        $flashType = $analysis->error_message ? 'warning' : 'success';
        $message = $analysis->error_message
            ? "Analisis selesai ({$analysis->error_message})"
            : "Analisis regulasi dan substansi materi oleh Gemini 3.8 Flash berhasil diselesaikan.";

        return back()->with($flashType, $message);
    }
}
