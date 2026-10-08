<?php

namespace Tests\Feature\Admin;

use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_material()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-01',
            'title' => 'Training 1',
            'created_by' => $admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subject 1',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.subjects.materials.store', $subject), [
            'title' => 'Bahan Tayang',
            'type' => 'pdf',
            'description' => 'Deskripsi bahan',
            'order' => 1,
        ]);

        $response->assertRedirect(route('admin.subjects.show', $subject));

        $this->assertDatabaseHas('materials', [
            'subject_id' => $subject->id,
            'title' => 'Bahan Tayang',
            'type' => 'pdf',
            'description' => 'Deskripsi bahan',
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_update_material()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-02',
            'title' => 'Training 2',
            'created_by' => $admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subject 2',
            'sort_order' => 1,
        ]);

        $material = $subject->materials()->create([
            'title' => 'Judul Lama',
            'type' => 'pdf',
            'description' => 'Deskripsi lama',
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.materials.update', $material), [
            'title' => 'Judul Baru',
            'type' => 'video',
            'description' => 'Deskripsi baru',
            'order' => 2,
        ]);

        $response->assertRedirect(route('admin.subjects.show', $subject));

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'title' => 'Judul Baru',
            'type' => 'video',
            'description' => 'Deskripsi baru',
            'sort_order' => 2,
        ]);
    }

    public function test_admin_can_add_material_version()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-03',
            'title' => 'Training 3',
            'created_by' => $admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subject 3',
            'sort_order' => 1,
        ]);

        $material = $subject->materials()->create([
            'title' => 'Materi PDF',
            'type' => 'pdf',
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.materials.versions.store', $material), [
            'google_drive_id' => 'https://drive.google.com/file/d/1a2b3c4d5e/view',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.materials.show', $material));

        $this->assertDatabaseHas('material_versions', [
            'material_id' => $material->id,
            'version_number' => 1,
            'gdrive_file_id' => '1a2b3c4d5e',
            'created_by' => $admin->id,
        ]);

        $material->refresh();
        $this->assertNotNull($material->current_version_id);
    }
}
