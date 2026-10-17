<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use App\Services\TrainingImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('pengembang');
        Role::findOrCreate('reviewer');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->developer = User::factory()->create([
            'name' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'email' => 'budi.santoso@example.com',
        ]);
        $this->developer->assignRole('pengembang');

        $this->reviewer = User::factory()->create([
            'name' => 'Agus Reviewer',
            'nip' => '198002022005012002',
            'email' => 'agus.reviewer@example.com',
        ]);
        $this->reviewer->assignRole('reviewer');
    }

    public function test_admin_can_download_excel_template(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.trainings.import.template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type') ?? ''
        );
        $this->assertStringContainsString(
            'Template_Import_Pelatihan',
            $response->headers->get('content-disposition') ?? ''
        );
    }

    public function test_non_admin_cannot_import_or_download_template(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.trainings.import.template'));
        $response->assertForbidden();

        $responsePost = $this->actingAs($user)
            ->post(route('admin.trainings.import'), []);
        $responsePost->assertForbidden();
    }

    public function test_admin_can_import_trainings_subjects_materials_and_assignments(): void
    {
        // Create spreadsheet with test data
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Nama Pelatihan',
            'Nama Mata Pelatihan',
            'Tipe Materi',
            'Link Materi',
            'Pengembang',
            'Reviewer',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $rows = [
            [
                'Microlearning Pembuatan Konten Edukasi',
                'Desain Grafis Pembelajaran',
                'video',
                'https://drive.google.com/file/d/1dummy_drive_id_123/view',
                $this->developer->nip,
                $this->reviewer->nip,
            ],
            [
                'Microlearning Pembuatan Konten Edukasi',
                'Desain Grafis Pembelajaran',
                'pdf',
                'https://drive.google.com/file/d/1dummy_drive_id_456/view',
                $this->developer->email,
                $this->reviewer->email,
            ],
            [
                'Pelatihan Pengelolaan Anggaran',
                'Akuntansi Keuangan',
                'video',
                'https://drive.google.com/file/d/1dummy_drive_id_789/view',
                $this->developer->name,
                $this->reviewer->name,
            ],
        ];
        $sheet->fromArray($rows, null, 'A2');

        $tempFile = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'import_data.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)
            ->post(route('admin.trainings.import'), [
                'file' => $uploadedFile,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify Trainings created
        $this->assertDatabaseHas('trainings', [
            'title' => 'Microlearning Pembuatan Konten Edukasi',
        ]);
        $this->assertDatabaseHas('trainings', [
            'title' => 'Pelatihan Pengelolaan Anggaran',
        ]);

        // Verify Subjects created
        $training1 = Training::where('title', 'Microlearning Pembuatan Konten Edukasi')->firstOrFail();
        $this->assertDatabaseHas('subjects', [
            'training_id' => $training1->id,
            'title' => 'Desain Grafis Pembelajaran',
        ]);

        $subject1 = Subject::where('training_id', $training1->id)->where('title', 'Desain Grafis Pembelajaran')->firstOrFail();

        // Verify Assignments created
        $this->assertDatabaseHas('subject_user', [
            'subject_id' => $subject1->id,
            'user_id' => $this->developer->id,
            'role' => 'pengembang',
        ]);
        $this->assertDatabaseHas('subject_user', [
            'subject_id' => $subject1->id,
            'user_id' => $this->reviewer->id,
            'role' => 'reviewer',
        ]);

        // Verify Materials created
        $this->assertDatabaseHas('materials', [
            'subject_id' => $subject1->id,
            'type' => 'video',
        ]);
        $this->assertDatabaseHas('materials', [
            'subject_id' => $subject1->id,
            'type' => 'pdf',
        ]);

        $material1 = Material::where('subject_id', $subject1->id)
            ->where('type', 'video')
            ->firstOrFail();

        // Verify Material Versions and Google Drive link created
        $this->assertDatabaseHas('material_versions', [
            'material_id' => $material1->id,
            'gdrive_url' => 'https://drive.google.com/file/d/1dummy_drive_id_123/view',
            'gdrive_file_id' => '1dummy_drive_id_123',
        ]);

        // Verify Review Assignment for reviewer created
        $this->assertDatabaseHas('review_assignments', [
            'material_id' => $material1->id,
            'reviewer_id' => $this->reviewer->id,
        ]);

        @unlink($tempFile);
    }

    public function test_import_fails_gracefully_when_required_columns_are_missing(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Kolom Tidak Dikenal', 'Data Lain']], null, 'A1');
        $sheet->fromArray([['Val 1', 'Val 2']], null, 'A2');

        $tempFile = tempnam(sys_get_temp_dir(), 'test_invalid_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'invalid.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)
            ->post(route('admin.trainings.import'), [
                'file' => $uploadedFile,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Nama Pelatihan', session('error'));

        @unlink($tempFile);
    }

    public function test_can_import_multiple_materials_in_single_subject_row_using_multi_links(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Nama Pelatihan',
            'Nama Mata Pelatihan',
            'Tipe Materi',
            'Link Materi',
            'Pengembang',
            'Reviewer',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $rows = [
            [
                'Pelatihan Digital Mindset',
                'Fondasi Transformasi Digital',
                'video, pdf',
                "https://drive.google.com/file/d/multilink_id_111/view\nhttps://drive.google.com/file/d/multilink_id_222/view",
                $this->developer->name,
                $this->reviewer->name,
            ],
            [
                'Pelatihan Digital Mindset',
                'Komunikasi Digital',
                'video',
                "https://drive.google.com/file/d/multilink_id_333/view, https://drive.google.com/file/d/multilink_id_444/view",
                $this->developer->name,
                $this->reviewer->name,
            ],
            [
                'Pelatihan Digital Mindset',
                'Regulasi dan Keamanan Data',
                'video, pdf, scorm',
                "https://drive.google.com/file/d/multilink_id_vid/view\nhttps://drive.google.com/file/d/multilink_id_doc/view\nhttps://drive.google.com/file/d/multilink_id_scorm/view",
                $this->developer->name,
                $this->reviewer->name,
            ],
        ];
        $sheet->fromArray($rows, null, 'A2');

        $tempFile = tempnam(sys_get_temp_dir(), 'test_multilink_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'multilink.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)
            ->post(route('admin.trainings.import'), [
                'file' => $uploadedFile,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $training = Training::where('title', 'Pelatihan Digital Mindset')->firstOrFail();
        $this->assertEquals(3, $training->subjects()->count());

        // Mata Pelatihan 1: Fondasi Transformasi Digital -> 2 materi (1 video, 1 pdf)
        $subject1 = $training->subjects()->where('title', 'Fondasi Transformasi Digital')->firstOrFail();
        $this->assertEquals(2, $subject1->materials()->count());

        $mat1_1 = $subject1->materials()->whereHas('versions', fn ($q) => $q->where('gdrive_file_id', 'multilink_id_111'))->firstOrFail();
        $this->assertEquals(\App\Enums\MaterialType::Video, $mat1_1->type);

        $mat1_2 = $subject1->materials()->whereHas('versions', fn ($q) => $q->where('gdrive_file_id', 'multilink_id_222'))->firstOrFail();
        $this->assertEquals(\App\Enums\MaterialType::Pdf, $mat1_2->type);

        $this->assertDatabaseHas('material_versions', [
            'gdrive_url' => 'https://drive.google.com/file/d/multilink_id_111/view',
            'gdrive_file_id' => 'multilink_id_111',
        ]);
        $this->assertDatabaseHas('material_versions', [
            'gdrive_url' => 'https://drive.google.com/file/d/multilink_id_222/view',
            'gdrive_file_id' => 'multilink_id_222',
        ]);

        // Reviewer terhubung ke kedua materi
        foreach ($subject1->materials as $mat) {
            $this->assertDatabaseHas('review_assignments', [
                'material_id' => $mat->id,
                'reviewer_id' => $this->reviewer->id,
            ]);
        }

        // Mata Pelatihan 2: Komunikasi Digital -> 2 materi dipisahkan koma
        $subject2 = $training->subjects()->where('title', 'Komunikasi Digital')->firstOrFail();
        $this->assertEquals(2, $subject2->materials()->count());

        $this->assertDatabaseHas('material_versions', [
            'gdrive_url' => 'https://drive.google.com/file/d/multilink_id_333/view',
            'gdrive_file_id' => 'multilink_id_333',
        ]);
        $this->assertDatabaseHas('material_versions', [
            'gdrive_url' => 'https://drive.google.com/file/d/multilink_id_444/view',
            'gdrive_file_id' => 'multilink_id_444',
        ]);

        // Mata Pelatihan 3: Regulasi dan Keamanan Data -> 3 materi (video, pdf, scorm)
        $subject3 = $training->subjects()->where('title', 'Regulasi dan Keamanan Data')->firstOrFail();
        $this->assertEquals(3, $subject3->materials()->count());

        $mat3_1 = $subject3->materials()->whereHas('versions', fn ($q) => $q->where('gdrive_file_id', 'multilink_id_vid'))->firstOrFail();
        $this->assertEquals(\App\Enums\MaterialType::Video, $mat3_1->type);

        $mat3_2 = $subject3->materials()->whereHas('versions', fn ($q) => $q->where('gdrive_file_id', 'multilink_id_doc'))->firstOrFail();
        $this->assertEquals(\App\Enums\MaterialType::Pdf, $mat3_2->type);

        $mat3_3 = $subject3->materials()->whereHas('versions', fn ($q) => $q->where('gdrive_file_id', 'multilink_id_scorm'))->firstOrFail();
        $this->assertEquals(\App\Enums\MaterialType::Scorm, $mat3_3->type);

        @unlink($tempFile);
    }
}
