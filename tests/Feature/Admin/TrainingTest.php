<?php

namespace Tests\Feature\Admin;

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_training_index_with_pagination(): void
    {
        Training::factory()->count(15)->create([
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.trainings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/trainings/index')
            ->has('trainings.data', 10)
            ->has('trainings.links')
            ->where('trainings.total', 15)
        );
    }

    public function test_can_filter_trainings_by_search_name(): void
    {
        Training::factory()->create([
            'title' => 'Pelatihan Kepemimpinan Administrator',
            'code' => 'PKA-2026',
            'created_by' => $this->admin->id,
        ]);

        Training::factory()->create([
            'title' => 'Pelatihan Teknis Keuangan',
            'code' => 'PTK-2026',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.trainings.index', [
            'search' => 'Kepemimpinan',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/trainings/index')
            ->has('trainings.data', 1)
            ->where('trainings.data.0.title', 'Pelatihan Kepemimpinan Administrator')
        );
    }

    public function test_can_filter_trainings_by_status(): void
    {
        Training::factory()->create([
            'title' => 'Pelatihan Satu',
            'status' => 'Sedang Review',
            'created_by' => $this->admin->id,
        ]);

        Training::factory()->create([
            'title' => 'Pelatihan Dua',
            'status' => 'Selesai Review',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.trainings.index', [
            'status' => 'Selesai Review',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/trainings/index')
            ->has('trainings.data', 1)
            ->where('trainings.data.0.title', 'Pelatihan Dua')
        );
    }

    public function test_can_create_training_with_status_defaulting_to_sedang_review(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.trainings.store'), [
            'code' => 'PLT-BARU-01',
            'title' => 'Pelatihan Baru Mantap',
            'description' => 'Deskripsi pelatihan baru',
        ]);

        $response->assertRedirect(route('admin.trainings.index'));
        $this->assertDatabaseHas('trainings', [
            'code' => 'PLT-BARU-01',
            'status' => 'Sedang Review',
        ]);
    }

    public function test_can_update_training_status(): void
    {
        $training = Training::factory()->create([
            'status' => 'Sedang Review',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.trainings.update', $training), [
            'code' => $training->code,
            'title' => $training->title,
            'description' => $training->description,
            'status' => 'Selesai Review',
        ]);

        $response->assertRedirect(route('admin.trainings.index'));
        $this->assertEquals('Selesai Review', $training->fresh()->status);
    }

    public function test_can_update_training_status_in_bulk(): void
    {
        $t1 = Training::factory()->create([
            'status' => 'Sedang Review',
            'created_by' => $this->admin->id,
        ]);
        $t2 = Training::factory()->create([
            'status' => 'Sedang Review',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.trainings.status.bulk'), [
            'training_ids' => [$t1->id, $t2->id],
            'status' => 'Selesai Review',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Selesai Review', $t1->fresh()->status);
        $this->assertEquals('Selesai Review', $t2->fresh()->status);
    }

    public function test_can_export_training_report_csv(): void
    {
        $training = Training::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.trainings.report', $training));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type') ?? '');
        $this->assertStringContainsString('laporan_ulasan_', $response->headers->get('content-disposition') ?? '');
    }
}

