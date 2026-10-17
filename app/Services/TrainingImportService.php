<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Enums\ReviewStatus;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrainingImportService
{
    /**
     * Generate template spreadsheet (.xlsx) with example data and dropdowns for developers and reviewers.
     */
    public function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pelatihan');

        $headers = [
            'Nama Pelatihan',
            'Nama Mata Pelatihan',
            'Tipe Materi',
            'Link Materi',
            'Pengembang',
            'Reviewer',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Sample data rows (with example of multiple material links in one cell)
        $sampleData = [
            [
                'Microlearning Pembuatan Konten Edukasi',
                'Desain Grafis Pembelajaran',
                'video, pdf',
                "https://drive.google.com/file/d/1example_drive_id_123/view\nhttps://drive.google.com/file/d/1example_drive_id_456/view",
                '',
                '',
            ],
            [
                'Pelatihan Pengelolaan Keuangan Negara',
                'Akuntansi Pemerintah Pusat',
                'video',
                'https://drive.google.com/file/d/1example_drive_id_789/view',
                '',
                '',
            ],
            [
                'Pelatihan Pengadaan Barang dan Jasa',
                'Tata Kelola dan Regulasi PBJ',
                'video, pdf, scorm',
                "https://drive.google.com/file/d/1example_pbj_video_101/view\nhttps://drive.google.com/file/d/1example_pbj_modul_102/view\nhttps://drive.google.com/file/d/1example_pbj_scorm_103/view",
                '',
                '',
            ],
        ];

        $sheet->fromArray($sampleData, null, 'A2');

        // Styling Header
        $headerRange = 'A1:F1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E40AF'); // Indigo/Blue
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Border, wrap text, and column width
        $lastRow = count($sampleData) + 1;
        $sheet->getStyle("A1:F{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle("A1:F{$lastRow}")->getAlignment()->setWrapText(true);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Fetch developers and reviewers from database for dropdowns
        $developers = User::role('pengembang')->orderBy('name')->pluck('name')->toArray();
        if (empty($developers)) {
            $developers = User::orderBy('name')->pluck('name')->toArray();
        }

        $reviewers = User::role('reviewer')->orderBy('name')->pluck('name')->toArray();
        if (empty($reviewers)) {
            $reviewers = User::orderBy('name')->pluck('name')->toArray();
        }

        $materialTypeOptions = [
            'video',
            'pdf',
            'scorm',
            'video, pdf',
            'video, video',
            'pdf, pdf',
            'video, scorm',
            'pdf, scorm',
            'video, pdf, scorm',
        ];

        // Hidden Reference sheet for dropdown lists
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');

        foreach ($developers as $idx => $name) {
            $refSheet->setCellValue('A' . ($idx + 1), $name);
        }
        foreach ($reviewers as $idx => $name) {
            $refSheet->setCellValue('B' . ($idx + 1), $name);
        }
        foreach ($materialTypeOptions as $idx => $opt) {
            $refSheet->setCellValue('C' . ($idx + 1), $opt);
        }

        $refSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // Validation for Tipe Materi in Column C (supports single type or combinations like "video, pdf")
        $valType = new DataValidation();
        $valType->setType(DataValidation::TYPE_LIST);
        $valType->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $valType->setAllowBlank(true);
        $valType->setShowDropDown(true);
        $valType->setShowErrorMessage(false);
        $valType->setShowInputMessage(true);
        $valType->setPromptTitle('Tipe Materi');
        $valType->setPrompt('Pilih tipe dari daftar atau ketik kombinasi (contoh: video, pdf) berurutan sesuai Link Materi.');
        $valType->setFormula1("'Referensi'!\$C\$1:\$C\$" . count($materialTypeOptions));

        // Validation for Pengembang in Column E
        $valDev = null;
        if (!empty($developers)) {
            $valDev = new DataValidation();
            $valDev->setType(DataValidation::TYPE_LIST);
            $valDev->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $valDev->setAllowBlank(true);
            $valDev->setShowDropDown(true);
            $valDev->setShowInputMessage(true);
            $valDev->setPromptTitle('Pilih Pengembang');
            $valDev->setPrompt('Pilih nama pengembang dari daftar');
            $valDev->setFormula1("'Referensi'!\$A\$1:\$A\$" . count($developers));
        }

        // Validation for Reviewer in Column F
        $valRev = null;
        if (!empty($reviewers)) {
            $valRev = new DataValidation();
            $valRev->setType(DataValidation::TYPE_LIST);
            $valRev->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $valRev->setAllowBlank(true);
            $valRev->setShowDropDown(true);
            $valRev->setShowInputMessage(true);
            $valRev->setPromptTitle('Pilih Reviewer');
            $valRev->setPrompt('Pilih nama reviewer dari daftar');
            $valRev->setFormula1("'Referensi'!\$B\$1:\$B\$" . count($reviewers));
        }

        // Apply dropdown validations to rows 2 through 200
        for ($r = 2; $r <= 200; $r++) {
            $sheet->getCell("C{$r}")->setDataValidation(clone $valType);
            if ($valDev) {
                $sheet->getCell("E{$r}")->setDataValidation(clone $valDev);
            }
            if ($valRev) {
                $sheet->getCell("F{$r}")->setDataValidation(clone $valRev);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Import trainings, subjects, materials, and assignments from an uploaded spreadsheet file.
     *
     * @return array{
     *     trainings_count: int,
     *     subjects_count: int,
     *     materials_count: int,
     *     assignments_count: int,
     *     warnings: array<string>
     * }
     */
    public function import(string|UploadedFile $file, int $userId): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new \RuntimeException('File excel kosong.');
        }

        $headerRow = $rows[0];
        $colMap = $this->mapColumns($headerRow);

        if (!isset($colMap['training_title'])) {
            throw new \RuntimeException("Kolom 'Nama Pelatihan' tidak ditemukan pada file Excel. Pastikan nama kolom sesuai template.");
        }

        $trainingsCreated = 0;
        $subjectsCreated = 0;
        $materialsCreated = 0;
        $assignmentsCreated = 0;
        $warnings = [];

        DB::transaction(function () use (
            $rows,
            $colMap,
            $userId,
            &$trainingsCreated,
            &$subjectsCreated,
            &$materialsCreated,
            &$assignmentsCreated,
            &$warnings
        ) {
            $totalRows = count($rows);

            for ($i = 1; $i < $totalRows; $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;

                // Check if row is empty
                $nonEmptyCells = array_filter($row, fn ($val) => trim((string) $val) !== '');
                if (empty($nonEmptyCells)) {
                    continue;
                }

                $trainingTitle = trim((string) ($row[$colMap['training_title'] ?? -1] ?? ''));
                if ($trainingTitle === '') {
                    $warnings[] = "Baris {$rowNum}: Kolom Nama Pelatihan kosong, baris dilewati.";
                    continue;
                }

                $subjectTitle = trim((string) ($row[$colMap['subject_title'] ?? -1] ?? ''));
                $materialTypeRaw = trim((string) ($row[$colMap['material_type'] ?? -1] ?? ''));
                $materialLink = trim((string) ($row[$colMap['material_link'] ?? -1] ?? ''));
                $developerRaw = trim((string) ($row[$colMap['developer'] ?? -1] ?? ''));
                $reviewerRaw = trim((string) ($row[$colMap['reviewer'] ?? -1] ?? ''));

                // 1. Process Training
                $training = Training::where('title', $trainingTitle)->first();
                if (!$training) {
                    $training = Training::create([
                        'title' => $trainingTitle,
                        'created_by' => $userId,
                    ]);
                    $trainingsCreated++;
                }

                // If no subject is provided, continue to next row
                if ($subjectTitle === '') {
                    continue;
                }

                // 2. Process Subject
                $subject = Subject::where('training_id', $training->id)
                    ->where('title', $subjectTitle)
                    ->first();

                if (!$subject) {
                    $nextSortOrder = (Subject::where('training_id', $training->id)->max('sort_order') ?? 0) + 1;
                    $subject = Subject::create([
                        'training_id' => $training->id,
                        'title' => $subjectTitle,
                        'sort_order' => $nextSortOrder,
                    ]);
                    $subjectsCreated++;
                }

                // 3. Process Developers Assignment
                if ($developerRaw !== '') {
                    $devTokens = preg_split('/[,;\n]+/', $developerRaw);
                    foreach ($devTokens as $token) {
                        $token = trim($token);
                        if ($token === '') {
                            continue;
                        }

                        $user = $this->resolveUser($token);
                        if (!$user) {
                            $warnings[] = "Baris {$rowNum}: Pengembang '{$token}' tidak ditemukan di sistem.";
                            continue;
                        }

                        if (!$user->hasRole('pengembang')) {
                            $user->assignRole('pengembang');
                        }

                        $alreadyAssigned = $subject->assignedUsers()
                            ->where('users.id', $user->id)
                            ->wherePivot('role', 'pengembang')
                            ->exists();

                        if (!$alreadyAssigned) {
                            $subject->assignedUsers()->attach($user->id, [
                                'role' => 'pengembang',
                                'assigned_by' => $userId,
                            ]);
                            $assignmentsCreated++;

                            try {
                                \App\Facades\WhatsApp::notifySubjectAssignment($user, $subject, 'pengembang');
                            } catch (\Throwable) {
                                // Silent fallback for WhatsApp notifications
                            }
                        }
                    }
                }

                // 4. Process Reviewers Assignment
                if ($reviewerRaw !== '') {
                    $revTokens = preg_split('/[,;\n]+/', $reviewerRaw);
                    foreach ($revTokens as $token) {
                        $token = trim($token);
                        if ($token === '') {
                            continue;
                        }

                        $user = $this->resolveUser($token);
                        if (!$user) {
                            $warnings[] = "Baris {$rowNum}: Reviewer '{$token}' tidak ditemukan di sistem.";
                            continue;
                        }

                        if (!$user->hasRole('reviewer')) {
                            $user->assignRole('reviewer');
                        }

                        $alreadyAssigned = $subject->assignedUsers()
                            ->where('users.id', $user->id)
                            ->wherePivot('role', 'reviewer')
                            ->exists();

                        if (!$alreadyAssigned) {
                            $subject->assignedUsers()->attach($user->id, [
                                'role' => 'reviewer',
                                'assigned_by' => $userId,
                            ]);
                            $assignmentsCreated++;

                            try {
                                \App\Facades\WhatsApp::notifySubjectAssignment($user, $subject, 'reviewer');
                            } catch (\Throwable) {
                                // Silent fallback for WhatsApp notifications
                            }

                            // Automatically connect reviewer to existing materials with versions
                            foreach ($subject->materials as $mat) {
                                $v = $mat->currentVersion ?? $mat->versions()->latest()->first();
                                if ($v) {
                                    $assign = ReviewAssignment::firstOrCreate([
                                        'material_id' => $mat->id,
                                        'reviewer_id' => $user->id,
                                    ], [
                                        'assigned_by' => $userId,
                                        'status' => AssignmentStatus::Pending,
                                    ]);

                                    Review::firstOrCreate([
                                        'material_version_id' => $v->id,
                                        'reviewer_id' => $user->id,
                                    ], [
                                        'review_assignment_id' => $assign->id,
                                        'status' => ReviewStatus::Draft,
                                    ]);
                                }
                            }
                        }
                    }
                }

                // 5. Process Material & Link (Supports single or multiple links per row)
                $links = $this->parseMultipleLinks($materialLink);
                $types = $this->parseMultipleTypes($materialTypeRaw);

                // If no link is specified but material type is provided, create one draft material
                $linkList = !empty($links) ? $links : (($materialTypeRaw !== '') ? [''] : []);

                foreach ($linkList as $idx => $linkItem) {
                    $typeRawItem = $types[$idx] ?? '';
                    $type = $this->resolveMaterialType($typeRawItem, $linkItem, $types[0] ?? null);

                    // Check if material already exists for this subject by link
                    $material = null;
                    if ($linkItem !== '') {
                        $existingVersion = MaterialVersion::whereHas('material', function ($q) use ($subject) {
                            $q->where('subject_id', $subject->id);
                        })->where('gdrive_url', $linkItem)->first();

                        if ($existingVersion) {
                            $material = $existingVersion->material;
                        }
                    }

                    if (!$material) {
                        $nextOrder = (Material::where('subject_id', $subject->id)->max('sort_order') ?? 0) + 1;
                        $material = Material::create([
                            'subject_id' => $subject->id,
                            'type' => $type,
                            'sort_order' => $nextOrder,
                            'status' => $linkItem !== '' ? MaterialStatus::InReview : MaterialStatus::Draft,
                            'created_by' => $userId,
                        ]);
                        $materialsCreated++;
                    }

                    // Process Link & Version if provided
                    if ($linkItem !== '') {
                        $existingVersion = $material->versions()
                            ->where('gdrive_url', $linkItem)
                            ->first();

                        if (!$existingVersion) {
                            $driveId = $this->extractGoogleDriveId($linkItem);
                            $nextVerNumber = (int) ($material->versions()->max('version_number') ?? 0) + 1;

                            $version = $material->versions()->create([
                                'version_number' => $nextVerNumber,
                                'gdrive_url' => $linkItem,
                                'gdrive_file_id' => $driveId,
                                'created_by' => $userId,
                            ]);

                            $material->update([
                                'current_version_id' => $version->id,
                                'status' => MaterialStatus::InReview,
                            ]);

                            // Connect all reviewers currently assigned to the subject
                            foreach ($subject->reviewers as $reviewer) {
                                $assign = ReviewAssignment::firstOrCreate([
                                    'material_id' => $material->id,
                                    'reviewer_id' => $reviewer->id,
                                ], [
                                    'assigned_by' => $userId,
                                    'status' => AssignmentStatus::Pending,
                                ]);

                                Review::firstOrCreate([
                                    'material_version_id' => $version->id,
                                    'reviewer_id' => $reviewer->id,
                                ], [
                                    'review_assignment_id' => $assign->id,
                                    'status' => ReviewStatus::Draft,
                                ]);
                            }
                        }
                    }
                }
            }
        });

        return [
            'trainings_count' => $trainingsCreated,
            'subjects_count' => $subjectsCreated,
            'materials_count' => $materialsCreated,
            'assignments_count' => $assignmentsCreated,
            'warnings' => $warnings,
        ];
    }

    /**
     * Map Excel column headers to logical field names.
     *
     * @param array<int, mixed> $headerRow
     * @return array<string, int>
     */
    protected function mapColumns(array $headerRow): array
    {
        $colMap = [];

        foreach ($headerRow as $colIndex => $cellVal) {
            $norm = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string) $cellVal)));

            if (in_array($norm, ['namapelatihan', 'pelatihan', 'judulpelatihan', 'training', 'trainingtitle'])) {
                $colMap['training_title'] = $colIndex;
            } elseif (in_array($norm, ['namamatapelatihan', 'matapelatihan', 'judulmatapelatihan', 'subject', 'subjecttitle'])) {
                $colMap['subject_title'] = $colIndex;
            } elseif (in_array($norm, ['tipemateri', 'jenismateri', 'tipe', 'type', 'materialtype'])) {
                $colMap['material_type'] = $colIndex;
            } elseif (in_array($norm, ['linkmateri', 'tautanmateri', 'linkgoogledrive', 'link', 'url', 'urlmateri', 'googledrive'])) {
                $colMap['material_link'] = $colIndex;
            } elseif (in_array($norm, ['pengembang', 'pengembangmateri', 'developer', 'nippengembang', 'namapengembang'])) {
                $colMap['developer'] = $colIndex;
            } elseif (in_array($norm, ['reviewer', 'penelaah', 'nipreviewer', 'namareviewer'])) {
                $colMap['reviewer'] = $colIndex;
            }
        }

        return $colMap;
    }

    /**
     * Resolve user by NIP, email, or name.
     */
    protected function resolveUser(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        // 1. By NIP
        $user = User::where('nip', $identifier)->first();
        if ($user) {
            return $user;
        }

        // 2. By Email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($identifier)])->first();
            if ($user) {
                return $user;
            }
        }

        // 3. By Exact Name (case-insensitive)
        $user = User::whereRaw('LOWER(name) = ?', [strtolower($identifier)])->first();
        if ($user) {
            return $user;
        }

        // 4. By Partial Name
        return User::where('name', 'LIKE', '%' . $identifier . '%')->first();
    }

    /**
     * Determine MaterialType enum based on type string, link, and optional row fallback.
     */
    protected function resolveMaterialType(string $typeRaw, string $link, ?string $fallbackType = null): MaterialType
    {
        $norm = strtolower(trim($typeRaw));

        if (in_array($norm, ['pdf', 'document', 'dokumen'])) {
            return MaterialType::Pdf;
        }
        if (in_array($norm, ['scorm', 'interaktif', 'zip'])) {
            return MaterialType::Scorm;
        }
        if (in_array($norm, ['video', 'vidio', 'mp4'])) {
            return MaterialType::Video;
        }

        // Auto-detect from URL if type is not specifically provided
        $lowerLink = strtolower($link);
        if (str_ends_with($lowerLink, '.pdf') || str_contains($lowerLink, 'pdf')) {
            return MaterialType::Pdf;
        }
        if (str_ends_with($lowerLink, '.zip') || str_contains($lowerLink, 'scorm')) {
            return MaterialType::Scorm;
        }

        // Fallback to first provided type in the row if present
        if ($fallbackType !== null) {
            $normFallback = strtolower(trim($fallbackType));
            if (in_array($normFallback, ['pdf', 'document', 'dokumen'])) {
                return MaterialType::Pdf;
            }
            if (in_array($normFallback, ['scorm', 'interaktif', 'zip'])) {
                return MaterialType::Scorm;
            }
            if (in_array($normFallback, ['video', 'vidio', 'mp4'])) {
                return MaterialType::Video;
            }
        }

        return MaterialType::Video;
    }

    /**
     * Extract Google Drive ID from URL.
     */
    protected function extractGoogleDriveId(string $url): string
    {
        if (preg_match('/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return $url;
    }

    /**
     * Parse multiple links from a raw cell string.
     * Supports newlines (Alt+Enter in Excel), commas, semicolons, or whitespace delimiters.
     *
     * @return string[]
     */
    protected function parseMultipleLinks(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // 1. If newlines are present, prioritize splitting by line
        if (str_contains($raw, "\n") || str_contains($raw, "\r")) {
            $lines = preg_split('/[\r\n]+/', $raw);
            $links = [];
            foreach ($lines as $line) {
                $subTokens = preg_split('/[,;\s]+(?=https?:\/\/)/i', trim($line));
                foreach ($subTokens as $token) {
                    $clean = trim($token);
                    if ($clean !== '') {
                        $links[] = $clean;
                    }
                }
            }
            return array_values(array_unique($links));
        }

        // 2. Look for delimiter before http:// or https:// (e.g. comma, semicolon, space)
        $tokens = preg_split('/[,;\s]+(?=https?:\/\/)/i', $raw);
        if (count($tokens) > 1) {
            $links = [];
            foreach ($tokens as $token) {
                $clean = trim($token);
                if ($clean !== '') {
                    $links[] = $clean;
                }
            }
            return array_values(array_unique($links));
        }

        // 3. Fallback split by commas or semicolons
        $tokens = preg_split('/[,;]+/', $raw);
        $links = [];
        foreach ($tokens as $token) {
            $clean = trim($token);
            if ($clean !== '') {
                $links[] = $clean;
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * Parse multiple material types from a raw cell string.
     * Supports commas, newlines, slashes, or conjunctions ("dan"/"and").
     *
     * @return string[]
     */
    protected function parseMultipleTypes(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $tokens = preg_split('/(?:[\r\n,;\/]+|\s+(?:dan|and)\s+)/i', $raw);
        $types = [];
        foreach ($tokens as $token) {
            $clean = trim($token);
            if ($clean !== '') {
                $types[] = $clean;
            }
        }

        return array_values($types);
    }
}
