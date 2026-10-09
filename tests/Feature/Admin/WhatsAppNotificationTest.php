<?php

namespace Tests\Feature\Admin;

use App\Facades\WhatsApp;
use App\Models\Material;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $reviewer;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'pengembang']);
        Role::firstOrCreate(['name' => 'reviewer']);

        $this->admin = User::factory()->create(['phone' => '081299990001']);
        $this->admin->assignRole('admin');

        $this->developer = User::factory()->create([
            'name' => 'Budi Pengembang',
            'phone' => '081234567890',
        ]);
        $this->developer->assignRole('pengembang');

        $this->reviewer = User::factory()->create([
            'name' => 'Siti Reviewer',
            'phone' => '085712345678',
        ]);
        $this->reviewer->assignRole('reviewer');

        $training = Training::create([
            'code' => 'TR-WA-01',
            'title' => 'Pelatihan Notifikasi',
            'created_by' => $this->admin->id,
        ]);

        $this->subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan WhatsApp',
            'sort_order' => 1,
        ]);
    }

    public function test_phone_number_normalization()
    {
        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);

        $this->assertEquals('628123456789', $service->normalizePhone('0812-3456-789'));
        $this->assertEquals('628123456789', $service->normalizePhone('+62 812 3456 789'));
        $this->assertEquals('628123456789', $service->normalizePhone('8123456789'));
        $this->assertEquals('628123456789', $service->normalizePhone('628123456789'));
        $this->assertNull($service->normalizePhone(null));
        $this->assertNull($service->normalizePhone(''));
    }

    public function test_whatsapp_service_log_driver_sends_successfully()
    {
        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);

        $result = $service->send('081234567890', 'Uji coba pesan', 'log');
        $this->assertTrue($result);
    }

    public function test_whatsapp_service_skips_when_user_has_no_phone()
    {
        $userWithoutPhone = User::factory()->create(['phone' => null]);
        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);

        $result = $service->send($userWithoutPhone, 'Pesan untuk user tanpa nomor');
        $this->assertFalse($result);
    }

    public function test_admin_can_create_user_with_phone_number()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Pengguna Baru',
            'email' => 'pengguna.baru@example.com',
            'phone' => '081288889999',
            'password' => 'Password123!',
            'nip' => '199001012020',
            'unit_kerja' => 'Pusat Kurikulum',
            'is_active' => true,
            'roles' => ['reviewer'],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'pengguna.baru@example.com',
            'phone' => '081288889999',
        ]);
    }

    public function test_admin_can_update_user_phone_number()
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->developer), [
            'name' => 'Budi Pengembang Updated',
            'email' => $this->developer->email,
            'phone' => '081299998888',
            'nip' => '123456',
            'unit_kerja' => 'Tim Pengembang',
            'is_active' => true,
            'roles' => ['pengembang'],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->developer->refresh();
        $this->assertEquals('081299998888', $this->developer->phone);
    }

    public function test_subject_assignment_triggers_whatsapp_notification()
    {
        WhatsApp::shouldReceive('notifySubjectAssignment')
            ->once()
            ->withArgs(function ($user, $subject, $role) {
                return $user->id === $this->developer->id 
                    && $subject->id === $this->subject->id 
                    && $role === 'pengembang';
            })
            ->andReturn(true);

        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->developer->id,
            'role' => 'pengembang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_review_assignment_triggers_whatsapp_notification()
    {
        $material = Material::create([
            'subject_id' => $this->subject->id,
            'title' => 'Materi Tes',
            'type' => 'pdf',
            'sort_order' => 1,
            'created_by' => $this->admin->id,
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'status' => 'draft',
            'gdrive_file_id' => '12345',
            'gdrive_url' => 'https://drive.google.com/file/d/12345/view',
            'created_by' => $this->developer->id,
        ]);

        WhatsApp::shouldReceive('notifyReviewAssignment')
            ->once()
            ->withArgs(function ($user, $ver, $review) use ($version) {
                return $user->id === $this->reviewer->id 
                    && $ver->id === $version->id;
            })
            ->andReturn(true);

        $response = $this->actingAs($this->admin)->post(route('admin.versions.assign', $version), [
            'user_id' => $this->reviewer->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_artisan_whatsapp_test_command_runs_successfully()
    {
        $this->artisan('whatsapp:test', [
            'phone' => '081234567890',
            'message' => 'Pesan uji coba',
            '--driver' => 'log',
        ])
        ->expectsOutputToContain('Uji Coba WhatsApp Gateway')
        ->expectsOutputToContain('6281234567890')
        ->expectsOutputToContain('Berhasil')
        ->assertExitCode(0);
    }
}
