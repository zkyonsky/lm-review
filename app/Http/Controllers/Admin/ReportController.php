<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ReviewPeriod;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function exportComments(Request $request, ?Training $training = null)
    {
        $trainingId = $training?->id ?? $request->query('training_id');
        $periodId = $request->query('period_id');

        $trainingModel = $training ?? ($trainingId ? Training::find($trainingId) : null);
        $periodModel = $periodId ? ReviewPeriod::find($periodId) : null;

        $prefix = '';
        if ($periodModel) {
            $prefix .= Str::slug($periodModel->name) . '_';
        }
        if ($trainingModel) {
            $prefix .= Str::slug($trainingModel->title) . '_';
        }
        if (!$prefix) {
            $prefix = 'lm_review_';
        }
        $filename = "laporan_ulasan_{$prefix}" . date('Y-m-d_H-i') . ".csv";

        $headers = [
            "Content-Type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"{$filename}\"",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'No',
            'Pelatihan',
            'Mata Pelatihan',
            'Materi',
            'Versi',
            'Reviewer',
            'Status Reviu',
            'Kesimpulan Reviu',
            'Catatan Umum',
            'Penulis Komentar',
            'Komentar / Catatan',
            'Tipe Anchor',
            'Status Komentar',
            'Waktu Komentar',
            'Diselesaikan Oleh',
            'Waktu Diselesaikan'
        ];

        // Eager load structure: Training -> Subjects -> Materials -> Assignments -> Reviews -> Comments
        if ($periodModel) {
            $trainingsQuery = Training::where(function ($q) use ($periodId) {
                $q->whereHas('periods', function ($pq) use ($periodId) {
                    $pq->where('review_periods.id', $periodId);
                })->orWhereHas('subjects.materials.assignments', function ($aq) use ($periodId) {
                    $aq->where('review_period_id', $periodId);
                });
            });
            if ($trainingId) {
                $trainingsQuery->where('trainings.id', $trainingId);
            }
        } elseif ($trainingId) {
            $trainingsQuery = Training::where('id', $trainingId);
        } else {
            $trainingsQuery = Training::query();
        }

        $trainings = $trainingsQuery
            ->with([
                'subjects.reviewers:id,name',
                'subjects.materials.currentVersion',
                'subjects.materials.versions' => fn ($q) => $q->orderByDesc('version_number'),
                'subjects.materials.assignments' => function ($aq) {
                    $aq->with([
                        'reviewer:id,name',
                        'reviews' => function ($rq) {
                            $rq->with([
                                'comments' => function ($cq) {
                                    $cq->with(['user:id,name', 'addressedBy:id,name'])->oldest();
                                }
                            ]);
                        }
                    ]);
                }
            ])
            ->get();

        $callback = function() use ($trainings, $columns, $periodId) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for MS Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, $columns);

            $rowNumber = 1;

            foreach ($trainings as $trainingItem) {
                if ($trainingItem->subjects->isEmpty()) {
                    fputcsv($file, [
                        $rowNumber++,
                        $trainingItem->title,
                        '-',
                        '-',
                        '-',
                        '-',
                        'Belum Ada Mata Pelatihan',
                        '-',
                        '-',
                        '-',
                        '-',
                        '-',
                        '-',
                        '-',
                        '-',
                        '-',
                    ]);
                    continue;
                }

                foreach ($trainingItem->subjects as $subject) {
                    if ($subject->materials->isEmpty()) {
                        fputcsv($file, [
                            $rowNumber++,
                            $trainingItem->title,
                            $subject->title,
                            '-',
                            '-',
                            '-',
                            'Belum Ada Materi',
                            '-',
                            '-',
                            '-',
                            '-',
                            '-',
                            '-',
                            '-',
                            '-',
                            '-',
                        ]);
                        continue;
                    }

                    foreach ($subject->materials as $material) {
                        $version = $material->currentVersion ?? $material->versions->first();
                        $versionNumber = $version ? 'v' . $version->version_number : '-';

                        $allAssignments = $material->assignments;

                        if ($periodId) {
                            $periodAssignments = $allAssignments->filter(fn ($a) => (int)$a->review_period_id === (int)$periodId);
                            if ($periodAssignments->isNotEmpty()) {
                                $periodReviewerIds = $periodAssignments->pluck('reviewer_id')->filter()->all();
                                $nullAssignments = $allAssignments->filter(fn ($a) => is_null($a->review_period_id) && !in_array($a->reviewer_id, $periodReviewerIds));
                                $assignments = $periodAssignments->concat($nullAssignments);
                            } else {
                                $nullAssignments = $allAssignments->filter(fn ($a) => is_null($a->review_period_id));
                                $assignments = $nullAssignments->isNotEmpty() ? $nullAssignments : $allAssignments;
                            }
                        } else {
                            $assignments = $allAssignments;
                        }

                        $existingReviewerIds = $assignments->pluck('reviewer_id')->filter()->all();
                        $unassignedSubjectReviewers = $subject->reviewers->reject(fn ($r) => in_array($r->id, $existingReviewerIds));

                        if ($assignments->isEmpty() && $unassignedSubjectReviewers->isEmpty()) {
                            fputcsv($file, [
                                $rowNumber++,
                                $trainingItem->title,
                                $subject->title,
                                $material->title,
                                $versionNumber,
                                '-',
                                'Belum Ditugaskan',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                            ]);
                            continue;
                        }

                        foreach ($assignments as $assignment) {
                            $reviewerName = $assignment->reviewer->name ?? '-';
                            $review = $assignment->reviews->firstWhere('material_version_id', $version?->id) ?? $assignment->reviews->first();

                            $isAssignmentSubmitted = $assignment->status === AssignmentStatus::Submitted 
                                || ($assignment->status instanceof \BackedEnum ? $assignment->status->value === 'submitted' : $assignment->status === 'submitted');

                            $isReviewSubmitted = $review?->status === ReviewStatus::Submitted
                                || ($review?->status instanceof \BackedEnum ? $review->status->value === 'submitted' : $review?->status === 'submitted');

                            $isSubmitted = $isAssignmentSubmitted || $isReviewSubmitted;

                            $isAssignmentInProgress = $assignment->status === AssignmentStatus::InProgress
                                || ($assignment->status instanceof \BackedEnum ? $assignment->status->value === 'in_progress' : $assignment->status === 'in_progress');

                            $isReviewDraftWithContent = $review && (
                                $review->status === ReviewStatus::Draft || ($review->status instanceof \BackedEnum ? $review->status->value === 'draft' : $review->status === 'draft')
                            ) && ($review->comments->isNotEmpty() || $review->general_notes);

                            if ($isSubmitted) {
                                $statusReviu = 'Selesai Direviu';
                            } elseif ($isAssignmentInProgress || $isReviewDraftWithContent) {
                                $statusReviu = 'Sedang Direviu';
                            } else {
                                $statusReviu = 'Belum Direviu';
                            }

                            $kesimpulan = $review?->conclusion?->label() ?? ($review?->conclusion?->value ?? '-');
                            $catatanUmum = $review?->general_notes ?: '-';

                            $comments = $review ? $review->comments : collect();

                            if ($comments->isEmpty()) {
                                $komentarText = $isSubmitted ? 'Tidak ada catatan revisi' : '-';
                                fputcsv($file, [
                                    $rowNumber++,
                                    $trainingItem->title,
                                    $subject->title,
                                    $material->title,
                                    $versionNumber,
                                    $reviewerName,
                                    $statusReviu,
                                    $kesimpulan,
                                    $catatanUmum,
                                    '-',
                                    $komentarText,
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                    '-',
                                ]);
                            } else {
                                foreach ($comments as $comment) {
                                    $anchorType = $comment->anchor_type instanceof \BackedEnum
                                        ? $comment->anchor_type->value
                                        : ($comment->anchor_type ?? '-');
                                    $statusComment = $comment->status instanceof \BackedEnum
                                        ? $comment->status->value
                                        : ($comment->status ?? '-');

                                    $authorName = $comment->user->name ?? '-';
                                    if ($comment->parent_id) {
                                        $authorName .= ' (Balasan)';
                                    }

                                    fputcsv($file, [
                                        $rowNumber++,
                                        $trainingItem->title,
                                        $subject->title,
                                        $material->title,
                                        $versionNumber,
                                        $reviewerName,
                                        $statusReviu,
                                        $kesimpulan,
                                        $catatanUmum,
                                        $authorName,
                                        $comment->body,
                                        $anchorType,
                                        $statusComment,
                                        $comment->created_at ? $comment->created_at->format('Y-m-d H:i:s') : '-',
                                        $comment->addressedBy->name ?? '-',
                                        $comment->addressed_at ? $comment->addressed_at->format('Y-m-d H:i:s') : '-',
                                    ]);
                                }
                            }
                        }

                        foreach ($unassignedSubjectReviewers as $reviewer) {
                            fputcsv($file, [
                                $rowNumber++,
                                $trainingItem->title,
                                $subject->title,
                                $material->title,
                                $versionNumber,
                                $reviewer->name,
                                'Belum Direviu',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                                '-',
                            ]);
                        }
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
