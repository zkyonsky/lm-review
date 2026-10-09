<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class StmkService
{
    /**
     * Cari path template file di folder storage.
     */
    public function getTemplatePath(string $type): ?string
    {
        $names = $type === 'pengembangan'
            ? ['template STMK Pengembangan.docx', 'STMK Pengembangan.docx', 'Template STMK Pengembangan.docx']
            : ['STMK Reviu.docx', 'template STMK Reviu.docx', 'Template STMK Reviu.docx'];

        $locations = [
            storage_path('app/public'),
            storage_path('app'),
            storage_path(''),
            storage_path('templates'),
        ];

        foreach ($locations as $dir) {
            foreach ($names as $name) {
                $path = $dir . DIRECTORY_SEPARATOR . $name;
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Bersihkan XML di dalam bracket [...] agar macro tidak terpecah oleh tag styling/proofErr Word.
     */
    protected function cleanDocumentXml(string $xml): string
    {
        return preg_replace_callback('/\[(.*?)\]/s', function ($matches) {
            $inner = preg_replace('/<[^>]+>/', '', $matches[1]);
            return '[' . $inner . ']';
        }, $xml);
    }

    /**
     * Buat TemplateProcessor yang sudah dibersihkan tag bracket-nya.
     */
    protected function createProcessor(string $templatePath): TemplateProcessor
    {
        $tempTemplate = tempnam(sys_get_temp_dir(), 'stmk_tpl_');
        copy($templatePath, $tempTemplate);

        $zip = new ZipArchive();
        if ($zip->open($tempTemplate) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml !== false) {
                $cleaned = $this->cleanDocumentXml($xml);
                $zip->addFromString('word/document.xml', $cleaned);
            }
            $zip->close();
        }

        $tp = new TemplateProcessor($tempTemplate);
        $tp->setMacroChars('[', ']');
        @unlink($tempTemplate);

        return $tp;
    }

    /**
     * Ambil daftar penugasan per mata pelatihan (1 orang 1 mata pelatihan).
     *
     * @return Collection<int, array{user: User, subject: Subject, training: Training}>
     */
    public function getAssignments(Training $training, string $type): Collection
    {
        $relation = $type === 'pengembangan' ? 'subjects.developers' : 'subjects.reviewers';
        $training->loadMissing([$relation]);

        $assignments = collect();

        foreach ($training->subjects as $subject) {
            $users = $type === 'pengembangan' ? $subject->developers : $subject->reviewers;
            foreach ($users as $user) {
                $assignments->push([
                    'user' => $user,
                    'subject' => $subject,
                    'training' => $training,
                ]);
            }
        }

        return $assignments;
    }

    /**
     * Generate file DOCX STMK Pengembangan untuk satu penugasan (1 orang, 1 mata pelatihan).
     * Catatan: [@NomorND] dan [@TanggalND] diabaikan (jangan diisi data, dibiarkan sesuai template).
     */
    public function generatePengembanganDoc(User $user, Subject $subject, Training $training): string
    {
        $templatePath = $this->getTemplatePath('pengembangan');
        if (!$templatePath) {
            throw new \RuntimeException('File template STMK Pengembangan.docx tidak ditemukan di folder storage.');
        }

        $tp = $this->createProcessor($templatePath);

        // Abaikan [@NomorND] dan [@TanggalND] - dibiarkan tetap seperti di template
        $tp->setValue('nama_pengembang', $user->name);
        $tp->setValue('nip_pengembang', $user->nip ?: '-');
        $tp->setValue('panggol_pengembang', $user->pangkat_golongan ?: '-');
        $tp->setValue('jabatan_pengembang', $user->jabatan ?: '-');
        $tp->setValue('nama_mata_pelatihan', $subject->title);
        $tp->setValue('nama_pelatihan', $training->title);

        $tempFile = tempnam(sys_get_temp_dir(), 'stmk_dev_') . '.docx';
        $tp->saveAs($tempFile);

        return $tempFile;
    }

    /**
     * Generate file DOCX STMK Reviu untuk satu penugasan (1 orang, 1 mata pelatihan).
     * Catatan: [@NomorND] dan [@TanggalND] diabaikan (jangan diisi data, dibiarkan sesuai template).
     */
    public function generateReviuDoc(User $user, Subject $subject, Training $training): string
    {
        $templatePath = $this->getTemplatePath('reviu');
        if (!$templatePath) {
            throw new \RuntimeException('File template STMK Reviu.docx tidak ditemukan di folder storage.');
        }

        $tp = $this->createProcessor($templatePath);

        // Abaikan [@NomorND] dan [@TanggalND] - dibiarkan tetap seperti di template
        $tp->setValue('@KopSurat', '');
        $tp->setValue('nama_reviewer', $user->name);
        $tp->setValue('nip_reviewer', $user->nip ?: '-');
        $tp->setValue('panggol_reviewer', $user->pangkat_golongan ?: '-');
        $tp->setValue('jabatan_reviewer', $user->jabatan ?: '-');
        $tp->setValue('nama_mata_pelatihan', $subject->title);
        $tp->setValue('nama_pelatihan', $training->title);

        $tempFile = tempnam(sys_get_temp_dir(), 'stmk_rev_') . '.docx';
        $tp->saveAs($tempFile);

        return $tempFile;
    }

    /**
     * Generate STMK untuk satu pelatihan per mata pelatihan (1 orang 1 mata pelatihan).
     * Mengembalikan single file DOCX jika hanya ada 1 penugasan, atau ZIP jika > 1 penugasan.
     *
     * @return array{success: bool, path?: string, filename?: string, message?: string}
     */
    public function generateForTraining(Training $training, string $type): array
    {
        $assignments = $this->getAssignments($training, $type);

        if ($assignments->isEmpty()) {
            $roleLabel = $type === 'pengembangan' ? 'pengembang materi' : 'reviewer';
            return [
                'success' => false,
                'message' => "Tidak ada {$roleLabel} yang ditugaskan pada mata pelatihan dalam pelatihan ini.",
            ];
        }

        $label = $type === 'pengembangan' ? 'Pengembangan' : 'Reviu';
        $trainingSlug = Str::slug($training->code ?: 'pelatihan');

        // Jika hanya ada 1 penugasan (1 orang 1 mata pelatihan)
        if ($assignments->count() === 1) {
            $single = $assignments->first();
            $path = $type === 'pengembangan'
                ? $this->generatePengembanganDoc($single['user'], $single['subject'], $training)
                : $this->generateReviuDoc($single['user'], $single['subject'], $training);

            $userNameClean = Str::slug($single['user']->name);
            $subjectNameClean = Str::slug($single['subject']->title);
            $filename = "STMK_{$label}_{$userNameClean}_{$subjectNameClean}.docx";

            return [
                'success' => true,
                'path' => $path,
                'filename' => $filename,
            ];
        }

        // Jika lebih dari 1 penugasan, satukan ke dalam file ZIP
        $zipPath = tempnam(sys_get_temp_dir(), 'stmk_zip_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $tempFiles = [];
        $existingNames = [];

        foreach ($assignments as $item) {
            $docPath = $type === 'pengembangan'
                ? $this->generatePengembanganDoc($item['user'], $item['subject'], $training)
                : $this->generateReviuDoc($item['user'], $item['subject'], $training);

            $tempFiles[] = $docPath;
            $userNameClean = Str::slug($item['user']->name);
            $subjectNameClean = Str::slug($item['subject']->title);

            $baseName = "STMK_{$label}_{$userNameClean}_{$subjectNameClean}.docx";
            $entryName = $baseName;
            $counter = 1;
            while (in_array($entryName, $existingNames)) {
                $entryName = "STMK_{$label}_{$userNameClean}_{$subjectNameClean}_{$counter}.docx";
                $counter++;
            }
            $existingNames[] = $entryName;

            $zip->addFile($docPath, $entryName);
        }

        $zip->close();

        // Hapus file docx temporer setelah masuk zip
        foreach ($tempFiles as $f) {
            @unlink($f);
        }

        $filename = "STMK_{$label}_{$trainingSlug}.zip";

        return [
            'success' => true,
            'path' => $zipPath,
            'filename' => $filename,
        ];
    }

    /**
     * Generate STMK massal untuk beberapa pelatihan sekaligus per mata pelatihan dalam format ZIP.
     *
     * @param array<int> $trainingIds
     * @return array{success: bool, path?: string, filename?: string, message?: string}
     */
    public function generateBulk(array $trainingIds, string $type): array
    {
        $trainings = Training::with(['subjects.developers', 'subjects.reviewers'])
            ->whereIn('id', $trainingIds)
            ->get();

        if ($trainings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada pelatihan yang dipilih.',
            ];
        }

        $label = $type === 'pengembangan' ? 'Pengembangan' : 'Reviu';
        $allDocs = []; // [ ['path' => ..., 'nameInZip' => ...] ]

        foreach ($trainings as $training) {
            $assignments = $this->getAssignments($training, $type);

            if ($assignments->isEmpty()) {
                continue;
            }

            $trainingSlug = Str::slug($training->code ?: "pelatihan-{$training->id}");
            $existingNames = [];

            foreach ($assignments as $item) {
                $docPath = $type === 'pengembangan'
                    ? $this->generatePengembanganDoc($item['user'], $item['subject'], $training)
                    : $this->generateReviuDoc($item['user'], $item['subject'], $training);

                $userNameClean = Str::slug($item['user']->name);
                $subjectNameClean = Str::slug($item['subject']->title);

                $baseName = "STMK_{$label}_{$userNameClean}_{$subjectNameClean}.docx";
                $entryName = $baseName;
                $counter = 1;
                while (in_array($entryName, $existingNames)) {
                    $entryName = "STMK_{$label}_{$userNameClean}_{$subjectNameClean}_{$counter}.docx";
                    $counter++;
                }
                $existingNames[] = $entryName;

                $allDocs[] = [
                    'path' => $docPath,
                    'nameInZip' => "{$trainingSlug}/{$entryName}",
                ];
            }
        }

        if (empty($allDocs)) {
            $roleLabel = $type === 'pengembangan' ? 'pengembang materi' : 'reviewer';
            return [
                'success' => false,
                'message' => "Tidak ada penugasan {$roleLabel} yang ditemukan pada pelatihan-pelatihan yang dipilih.",
            ];
        }

        // Jika hanya ada 1 dokumen dari semua pilihan
        if (count($allDocs) === 1) {
            $single = $allDocs[0];
            $baseName = basename($single['nameInZip']);
            return [
                'success' => true,
                'path' => $single['path'],
                'filename' => $baseName,
            ];
        }

        // Satukan ke file ZIP
        $zipPath = tempnam(sys_get_temp_dir(), 'stmk_bulk_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($allDocs as $doc) {
            $zip->addFile($doc['path'], $doc['nameInZip']);
        }

        $zip->close();

        // Hapus file-file sementara
        foreach ($allDocs as $doc) {
            @unlink($doc['path']);
        }

        $dateSuffix = date('Ymd_His');
        $filename = "STMK_{$label}_Massal_{$dateSuffix}.zip";

        return [
            'success' => true,
            'path' => $zipPath,
            'filename' => $filename,
        ];
    }
}
