<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewComment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function exportComments()
    {
        $comments = ReviewComment::with([
            'materialVersion.material.subject.training',
            'user',
            'review.user',
            'addressedBy'
        ])->get();

        $filename = "laporan_ulasan_lm_review_" . date('Y-m-d_H-i') . ".csv";
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
