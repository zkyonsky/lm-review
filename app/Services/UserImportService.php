<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\Models\Role;

class UserImportService
{
    /**
     * Menghasilkan Spreadsheet template Excel untuk impor data pengguna massal.
     */
    public function generateTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pengguna');

        $headers = [
            'Nama Lengkap',
            'Email',
            'Password',
            'Peran',
            'NIP',
            'No. WhatsApp',
            'Unit Kerja',
            'Jabatan',
            'Pangkat / Golongan',
            'Status',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Sample data rows
        $sampleData = [
            [
                'Ahmad Fauzi, S.Kom., M.T.',
                'ahmad.fauzi@kemenkeu.go.id',
                'password123',
                'pengembang',
                '198705122010121002',
                '081234567890',
                'Pusdiklat Anggaran dan Perbendaharaan',
                'Widyaiswara Ahli Muda',
                'Penata Tk. I / III/d',
                'Aktif',
            ],
            [
                'Dr. Siti Rahmawati, M.Ed.',
                'siti.rahmawati@kemenkeu.go.id',
                '',
                'reviewer',
                '198203152006042001',
                '082198765432',
                'Pusdiklat Keuangan Umum',
                'Widyaiswara Ahli Madya',
                'Pembina / IV/a',
                'Aktif',
            ],
            [
                'Bambang Wijaya, S.E.',
                'bambang.wijaya@kemenkeu.go.id',
                '',
                'pengembang',
                '199108222015021003',
                '085611223344',
                'BPPK Sekretariat',
                'Pranata Komputer Ahli Pertama',
                'Penata Muda Tk. I / III/b',
                'Aktif',
            ],
        ];

        $sheet->fromArray($sampleData, null, 'A2');

        // Header Styling (Navy / Indigo primary, bold white text)
        $headerRange = 'A1:J1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E40AF'); // Navy/Blue
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Borders & column auto width
        $lastRow = count($sampleData) + 1;
        $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Available roles from database or enum
        $roles = Role::orderBy('name')->pluck('name')->toArray();
        if (empty($roles)) {
            $roles = array_column(RoleEnum::options(), 'value');
        }

        $statuses = ['Aktif', 'Nonaktif'];

        // Hidden Reference sheet for Data Validation Dropdowns
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');

        foreach ($roles as $idx => $roleName) {
            $refSheet->setCellValue('A' . ($idx + 1), $roleName);
        }
        foreach ($statuses as $idx => $statusName) {
            $refSheet->setCellValue('B' . ($idx + 1), $statusName);
        }

        $refSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // Validation for Peran (Role) in Column D
        $valRole = new DataValidation();
        $valRole->setType(DataValidation::TYPE_LIST);
        $valRole->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $valRole->setAllowBlank(true);
        $valRole->setShowDropDown(true);
        $valRole->setShowInputMessage(true);
        $valRole->setPromptTitle('Pilih Peran');
        $valRole->setPrompt('Pilih peran pengguna (pengembang, reviewer, admin)');
        $valRole->setFormula1("'Referensi'!\$A\$1:\$A\$" . count($roles));

        // Validation for Status in Column J
        $valStatus = new DataValidation();
        $valStatus->setType(DataValidation::TYPE_LIST);
        $valStatus->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $valStatus->setAllowBlank(true);
        $valStatus->setShowDropDown(true);
        $valStatus->setShowInputMessage(true);
        $valStatus->setPromptTitle('Pilih Status');
        $valStatus->setPrompt('Pilih status akun (Aktif / Nonaktif)');
        $valStatus->setFormula1("'Referensi'!\$B\$1:\$B\$" . count($statuses));

        // Apply DataValidation to rows 2 up to 500
        for ($r = 2; $r <= 500; $r++) {
            $sheet->setDataValidation("D{$r}", clone $valRole);
            $sheet->setDataValidation("J{$r}", clone $valStatus);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Memproses import data pengguna dari berkas Excel.
     *
     * @return array{users_created: int, users_updated: int, warnings: string[]}
     */
    public function import(UploadedFile $file): array
    {
        $filePath = $file->getRealPath();
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows)) {
            throw new \RuntimeException('Berkas Excel kosong.');
        }

        $headerRow = $rows[0];
        $colMap = $this->mapColumns($headerRow);

        if (!isset($colMap['email']) || !isset($colMap['name'])) {
            throw new \RuntimeException("Kolom 'Nama Lengkap' dan 'Email' wajib ada pada berkas Excel.");
        }

        $usersCreated = 0;
        $usersUpdated = 0;
        $warnings = [];

        DB::transaction(function () use (
            $rows,
            $colMap,
            &$usersCreated,
            &$usersUpdated,
            &$warnings
        ) {
            $totalRows = count($rows);

            for ($i = 1; $i < $totalRows; $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;

                // Cek apakah baris kosong
                $nonEmptyCells = array_filter($row, fn ($val) => trim((string) $val) !== '');
                if (empty($nonEmptyCells)) {
                    continue;
                }

                $name = trim((string) ($row[$colMap['name'] ?? -1] ?? ''));
                $email = strtolower(trim((string) ($row[$colMap['email'] ?? -1] ?? '')));
                $passwordRaw = trim((string) ($row[$colMap['password'] ?? -1] ?? ''));
                $roleRaw = trim((string) ($row[$colMap['role'] ?? -1] ?? ''));
                $nip = trim((string) ($row[$colMap['nip'] ?? -1] ?? ''));
                $phoneRaw = trim((string) ($row[$colMap['phone'] ?? -1] ?? ''));
                $unitKerja = trim((string) ($row[$colMap['unit_kerja'] ?? -1] ?? ''));
                $jabatan = trim((string) ($row[$colMap['jabatan'] ?? -1] ?? ''));
                $pangkatGolongan = trim((string) ($row[$colMap['pangkat_golongan'] ?? -1] ?? ''));
                $statusRaw = strtolower(trim((string) ($row[$colMap['status'] ?? -1] ?? '')));

                // Validasi nama & email
                if ($name === '') {
                    $warnings[] = "Baris {$rowNum}: Kolom Nama kosong, baris dilewati.";
                    continue;
                }

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $warnings[] = "Baris {$rowNum}: Email '{$email}' tidak valid, baris dilewati.";
                    continue;
                }

                // Normalisasi Nomor WhatsApp / HP
                $phone = $this->normalizePhoneNumber($phoneRaw);

                // Normalisasi Status Akun
                $isActive = !in_array($statusRaw, ['nonaktif', 'inaktif', 'false', '0', 'tidak aktif', 'non-aktif'], true);

                // Cari apakah user sudah ada berdasarkan email
                $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

                if ($user) {
                    // Update user yang sudah ada
                    $updateData = [
                        'name' => $name,
                        'nip' => $nip !== '' ? $nip : $user->nip,
                        'phone' => $phone !== '' ? $phone : $user->phone,
                        'unit_kerja' => $unitKerja !== '' ? $unitKerja : $user->unit_kerja,
                        'jabatan' => $jabatan !== '' ? $jabatan : $user->jabatan,
                        'pangkat_golongan' => $pangkatGolongan !== '' ? $pangkatGolongan : $user->pangkat_golongan,
                        'is_active' => $isActive,
                    ];

                    if ($passwordRaw !== '') {
                        $updateData['password'] = Hash::make($passwordRaw);
                    }

                    $user->update($updateData);
                    $usersUpdated++;
                } else {
                    // Buat user baru
                    $password = $passwordRaw !== '' ? $passwordRaw : 'password';

                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'nip' => $nip !== '' ? $nip : null,
                        'phone' => $phone !== '' ? $phone : null,
                        'unit_kerja' => $unitKerja !== '' ? $unitKerja : null,
                        'jabatan' => $jabatan !== '' ? $jabatan : null,
                        'pangkat_golongan' => $pangkatGolongan !== '' ? $pangkatGolongan : null,
                        'is_active' => $isActive,
                        'email_verified_at' => now(),
                    ]);

                    $usersCreated++;
                }

                // Sinkronisasi Peran (Role)
                if ($roleRaw !== '') {
                    $roleNames = preg_split('/[,;\n]+/', $roleRaw);
                    $validRoles = [];

                    foreach ($roleNames as $roleToken) {
                        $cleanRole = strtolower(trim((string) $roleToken));
                        if ($cleanRole === '') {
                            continue;
                        }

                        // Alias matching
                        if (in_array($cleanRole, ['developer', 'pengembang materi', 'pengembang'], true)) {
                            $cleanRole = 'pengembang';
                        } elseif (in_array($cleanRole, ['reviewer', 'penelaah', 'reviu'], true)) {
                            $cleanRole = 'reviewer';
                        } elseif (in_array($cleanRole, ['admin', 'administrator'], true)) {
                            $cleanRole = 'admin';
                        }

                        // Pastikan role ada di database
                        $roleObj = Role::where('name', $cleanRole)->first();
                        if ($roleObj) {
                            $validRoles[] = $roleObj->name;
                        } else {
                            $warnings[] = "Baris {$rowNum}: Role '{$cleanRole}' tidak dikenal di sistem.";
                        }
                    }

                    if (!empty($validRoles)) {
                        $user->syncRoles($validRoles);
                    }
                }
            }
        });

        return [
            'users_created' => $usersCreated,
            'users_updated' => $usersUpdated,
            'warnings' => $warnings,
        ];
    }

    /**
     * Memetakan header Excel ke kunci atribut data.
     *
     * @param array<int, mixed> $headerRow
     * @return array<string, int>
     */
    protected function mapColumns(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $idx => $cellValue) {
            $header = strtolower(trim((string) $cellValue));
            $cleaned = preg_replace('/[^a-z0-9]/', '', $header);

            if (in_array($cleaned, ['namalengkap', 'nama', 'fullname', 'name'], true)) {
                $map['name'] = $idx;
            } elseif (in_array($cleaned, ['email', 'alamatemail', 'mail'], true)) {
                $map['email'] = $idx;
            } elseif (in_array($cleaned, ['password', 'katasandi', 'pass'], true)) {
                $map['password'] = $idx;
            } elseif (in_array($cleaned, ['peran', 'role', 'roles', 'hakakses'], true)) {
                $map['role'] = $idx;
            } elseif (in_array($cleaned, ['nip', 'nomorindukpegawai'], true)) {
                $map['nip'] = $idx;
            } elseif (in_array($cleaned, ['nowhatsapp', 'whatsapp', 'wa', 'nohp', 'hp', 'phone', 'telepon', 'notelepon'], true)) {
                $map['phone'] = $idx;
            } elseif (in_array($cleaned, ['unitkerja', 'unit', 'satker'], true)) {
                $map['unit_kerja'] = $idx;
            } elseif (in_array($cleaned, ['jabatan', 'position'], true)) {
                $map['jabatan'] = $idx;
            } elseif (in_array($cleaned, ['pangkatgolongan', 'pangkat', 'golongan'], true)) {
                $map['pangkat_golongan'] = $idx;
            } elseif (in_array($cleaned, ['status', 'statusakun', 'isactive'], true)) {
                $map['status'] = $idx;
            }
        }

        return $map;
    }

    /**
     * Menormalkan nomor telepon/WhatsApp ke format standar (contoh: 6281234567890).
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if ($phone === '') {
            return '';
        }

        // 08123... -> 628123...
        if (str_starts_with($phone, '08')) {
            return '62' . substr($phone, 1);
        }

        // 8123... -> 628123...
        if (str_starts_with($phone, '8') && strlen($phone) >= 9) {
            return '62' . $phone;
        }

        return $phone;
    }
}
