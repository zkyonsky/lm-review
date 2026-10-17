<?php

namespace Tests\Feature\Admin;

use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use App\Services\StmkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class StmkTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $reviewer;
    protected Training $training;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('pengembang');
        Role::findOrCreate('reviewer');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->developer = User::factory()->create([
            'name' => 'Pengembang Hebat',
            'nip' => '198501012010011001',
            'jabatan' => 'Pranata Komputer Ahli Muda',
            'pangkat_golongan' => 'Penata (III/c)',
        ]);
        $this->developer->assignRole('pengembang');

        $this->reviewer = User::factory()->create([
            'name' => 'Reviewer Ahli',
            'nip' => '198002022005012002',
            'jabatan' => 'Widyaiswara Ahli Madya',
            'pangkat_golongan' => 'Pembina (IV/a)',
        ]);
        $this->reviewer->assignRole('reviewer');

        $this->training = Training::create([
            'title' => 'Pelatihan Pengelolaan Keuangan',
            'created_by' => $this->admin->id,
        ]);

        $this->subject = Subject::create([
            'training_id' => $this->training->id,
            'title' => 'Mata Pelatihan Akuntansi',
            'code' => 'MP-01',
            'sort_order' => 1,
        ]);
    }

    public function test_cannot_generate_stmk_if_no_assigned_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk', ['training' => $this->training->id, 'type' => 'pengembangan']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_can_generate_stmk_pengembangan_for_training_single_subject(): void
    {
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk', ['training' => $this->training->id, 'type' => 'pengembangan']));

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition') ?? '';
        $this->assertStringContainsString('.docx', $disposition);
    }

    public function test_can_generate_stmk_reviu_for_training_single_subject(): void
    {
        $this->subject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk', ['training' => $this->training->id, 'type' => 'reviu']));

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition') ?? '';
        $this->assertStringContainsString('.docx', $disposition);
    }

    public function test_generates_zip_per_mata_pelatihan_when_multiple_subjects(): void
    {
        // Hubungkan developer ke subject 1
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        // Buat subject 2 dan hubungkan developer yang sama
        $subject2 = Subject::create([
            'training_id' => $this->training->id,
            'title' => 'Mata Pelatihan Anggaran',
            'code' => 'MP-02',
            'sort_order' => 2,
        ]);
        $subject2->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk', ['training' => $this->training->id, 'type' => 'pengembangan']));

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition') ?? '';
        // Karena ada 2 penugasan (2 mata pelatihan), harus mengembalikan ZIP
        $this->assertStringContainsString('.zip', $disposition);
    }

    public function test_generated_document_preserves_nomor_and_tanggal_nd_placeholders(): void
    {
        $stmkService = app(StmkService::class);
        $docxPath = $stmkService->generatePengembanganDoc($this->developer, $this->subject, $this->training);

        $this->assertFileExists($docxPath);

        $zip = new ZipArchive();
        $zip->open($docxPath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($docxPath);

        // [@NomorND] dan [@TanggalND] harus tetap ada tidak diganti
        $this->assertStringContainsString('[@NomorND]', $xml);
        $this->assertStringContainsString('[@TanggalND]', $xml);
        $this->assertStringContainsString($this->developer->name, $xml);
        $this->assertStringContainsString($this->subject->title, $xml);
    }

    public function test_can_generate_bulk_stmk_pengembangan(): void
    {
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk.bulk', [
                'type' => 'pengembangan',
                'training_ids' => [$this->training->id],
            ]));

        $response->assertOk();
    }

    public function test_uses_microlearning_templates_when_training_starts_with_microlearning(): void
    {
        $stmkService = app(StmkService::class);

        $microTraining = Training::create([
            'title' => 'Microlearning Pembuatan Konten Pembelajaran',
            'created_by' => $this->admin->id,
        ]);

        $microSubject = Subject::create([
            'training_id' => $microTraining->id,
            'title' => 'Desain Konten',
            'code' => 'MP-ML-01',
            'sort_order' => 1,
        ]);

        $microSubject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);
        $microSubject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        // Template path check
        $devTemplate = $stmkService->getTemplatePath('pengembangan', $microTraining);
        $revTemplate = $stmkService->getTemplatePath('reviu', $microTraining);

        $this->assertNotNull($devTemplate);
        $this->assertStringContainsString('Microleaning', $devTemplate);

        $this->assertNotNull($revTemplate);
        $this->assertStringContainsString('Microlearning', $revTemplate);

        // Generate development doc
        $docxPath = $stmkService->generatePengembanganDoc($this->developer, $microSubject, $microTraining);
        $this->assertFileExists($docxPath);
        @unlink($docxPath);

        // Generate reviu doc
        $docxRevPath = $stmkService->generateReviuDoc($this->reviewer, $microSubject, $microTraining);
        $this->assertFileExists($docxRevPath);
        @unlink($docxRevPath);

        // Web route generation test
        $responseDev = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk', ['training' => $microTraining->id, 'type' => 'pengembangan']));
        $responseDev->assertOk();
        $this->assertStringContainsString('.docx', $responseDev->headers->get('content-disposition') ?? '');

        $responseRev = $this->actingAs($this->admin)
            ->get(route('admin.trainings.stmk', ['training' => $microTraining->id, 'type' => 'reviu']));
        $responseRev->assertOk();
        $this->assertStringContainsString('.docx', $responseRev->headers->get('content-disposition') ?? '');
    }
}
