<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'pengembang']);
        Role::firstOrCreate(['name' => 'reviewer']);

        $this->admin = User::factory()->create(['name' => 'Admin Utama']);
        $this->admin->assignRole('admin');

        $this->developer = User::factory()->create(['name' => 'Dev']);
        $this->developer->assignRole('pengembang');
    }

    public function test_admin_can_download_user_import_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.import.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Template_Import_Pengguna_', $response->headers->get('content-disposition'));
    }

    public function test_non_admin_cannot_access_user_import(): void
    {
        $response = $this->actingAs($this->developer)->get(route('admin.users.import.template'));
        $response->assertForbidden();

        $responsePost = $this->actingAs($this->developer)->post(route('admin.users.import'), []);
        $responsePost->assertForbidden();
    }

    public function test_admin_can_import_new_and_update_existing_users(): void
    {
        // Existing user to be updated
        $existingUser = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@kemenkeu.go.id',
            'jabatan' => 'Staff Lama',
            'unit_kerja' => 'Unit Lama',
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

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

        $rows = [
            // User 1: Baru (Pengembang)
            [
                'Budi Hartono, S.Kom',
                'budi.hartono@kemenkeu.go.id',
                'secret123',
                'pengembang',
                '198501012010011001',
                '081234567890',
                'Pusdiklat AP',
                'Widyaiswara Muda',
                'Penata Tk. I / III/d',
                'Aktif',
            ],
            // User 2: Update existing user (Reviewer)
            [
                'Nama Baru Diperbarui',
                'lama@kemenkeu.go.id',
                '',
                'reviewer',
                '198902022012012002',
                '085612345678',
                'Pusdiklat Baru',
                'Widyaiswara Madya',
                'Pembina / IV/a',
                'Aktif',
            ],
            // User 3: Baru Nonaktif
            [
                'Doni Nonaktif',
                'doni@kemenkeu.go.id',
                '',
                'pengembang',
                '199503032018011003',
                '6287712345678',
                'Sekretariat',
                'Pelaksana',
                'Pengatur / II/c',
                'Nonaktif',
            ],
        ];
        $sheet->fromArray($rows, null, 'A2');

        $tempFile = tempnam(sys_get_temp_dir(), 'test_user_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'import_users.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        // Check User 1 created
        $this->assertDatabaseHas('users', [
            'name' => 'Budi Hartono, S.Kom',
            'email' => 'budi.hartono@kemenkeu.go.id',
            'nip' => '198501012010011001',
            'phone' => '6281234567890',
            'unit_kerja' => 'Pusdiklat AP',
            'jabatan' => 'Widyaiswara Muda',
            'is_active' => true,
        ]);

        $user1 = User::where('email', 'budi.hartono@kemenkeu.go.id')->firstOrFail();
        $this->assertTrue($user1->hasRole('pengembang'));

        // Check User 2 updated
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'name' => 'Nama Baru Diperbarui',
            'email' => 'lama@kemenkeu.go.id',
            'nip' => '198902022012012002',
            'phone' => '6285612345678',
            'unit_kerja' => 'Pusdiklat Baru',
            'jabatan' => 'Widyaiswara Madya',
        ]);

        $existingUser->refresh();
        $this->assertTrue($existingUser->hasRole('reviewer'));

        // Check User 3 (Nonaktif)
        $this->assertDatabaseHas('users', [
            'email' => 'doni@kemenkeu.go.id',
            'is_active' => false,
        ]);

        @unlink($tempFile);
    }
}
