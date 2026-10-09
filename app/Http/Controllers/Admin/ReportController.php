<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewComment;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function exportComments(Request $request, ?Training $training = null)
    {
        $trainingId = $training?->id ?? $request->query('training_id');

        $query = ReviewComment::with([
            'materialVersion.material.subject.training',
            'user',
            'review.user',
            'addressedBy'
        ]);

        if ($trainingId) {
            $query->whereHas('materialVersion.material.subject', function ($q) use ($trainingId) {
                $q->where('training_id', $trainingId);
            });
        }

        $comments = $query->get();

        $trainingModel = $training ?? ($trainingId ? Training::find($trainingId) : null);
        $slug = $trainingModel ? Str::slug($trainingModel->code ?: $trainingModel->title) . '_' : 'lm_review_';
        $filename = "laporan_ulasan_{$slug}" . date('Y-m-d_H-i') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'ID', 'Pelatihan', 'Mata Pelatihan', 'Materi', 'Versi', 
            'Penulis Komentar', 'Komentar', 'Tipe Anchor', 'Status', 
            'Waktu Komentar', 'Diselesaikan Oleh', 'Waktu Diselesaikan'
        ];

        $callback = function() use($comments, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($comments as $comment) {
                $row = [
                    $comment->id,
                    $comment->materialVersion->material->subject->training->title ?? '-',
                    $comment->materialVersion->material->subject->title ?? '-',
                    $comment->materialVersion->material->title ?? '-',
                    'v' . ($comment->materialVersion->version_number ?? '1'),
                    $comment->user->name ?? '-',
                    $comment->body,
                    $comment->anchor_type->value ?? '-',
                    $comment->status->value ?? '-',
                    $comment->created_at->format('Y-m-d H:i:s'),
                    $comment->addressedBy->name ?? '-',
                    $comment->addressed_at ? $comment->addressed_at->format('Y-m-d H:i:s') : '-'
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
