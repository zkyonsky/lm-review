<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TrainingImportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingImportController extends Controller
{
    /**
     * Download sample Excel template for bulk import.
     */
    public function downloadTemplate(TrainingImportService $importService): StreamedResponse
    {
        $spreadsheet = $importService->generateTemplate();

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'Template_Import_Pelatihan_' . date('Ymd') . '.xlsx';

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Handle bulk upload and import from Excel file.
     */
    public function import(Request $request, TrainingImportService $importService)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240', // 10MB
            ],
        ], [
            'file.required' => 'Silakan pilih file Excel untuk diunggah.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).',
            'file.max' => 'Ukuran file maksimal adalah 10MB.',
        ]);

        try {
            $result = $importService->import($request->file('file'), auth()->id());

            $summary = sprintf(
                'Impor data berhasil diproses! %d pelatihan, %d mata pelatihan, %d materi, dan %d penugasan.',
                $result['trainings_count'],
                $result['subjects_count'],
                $result['materials_count'],
                $result['assignments_count']
            );

            if (!empty($result['warnings'])) {
                $warningSummary = implode("\n", array_slice($result['warnings'], 0, 5));
                if (count($result['warnings']) > 5) {
                    $warningSummary .= "\n...dan " . (count($result['warnings']) - 5) . " peringatan lainnya.";
                }

                return redirect()->back()
                    ->with('success', $summary)
                    ->with('warning', 'Beberapa catatan saat impor: ' . $warningSummary);
            }

            return redirect()->back()->with('success', $summary);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memproses file impor: ' . $e->getMessage());
        }
    }
}
