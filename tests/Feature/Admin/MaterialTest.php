<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
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

    public function test_admin_material_order_auto_increments()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-04',
            'title' => 'Training 4',
            'created_by' => $admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subject 4',
            'sort_order' => 1,
        ]);

        // First material without order -> should be 1
        $this->actingAs($admin)->post(route('admin.subjects.materials.store', $subject), [
            'title' => 'Materi Auto 1',
            'type' => 'pdf',
        ]);

        $this->assertDatabaseHas('materials', [
            'subject_id' => $subject->id,
            'title' => 'Materi Auto 1',
            'sort_order' => 1,
        ]);

        // Second material without order -> should be 2
        $this->actingAs($admin)->post(route('admin.subjects.materials.store', $subject), [
            'title' => 'Materi Auto 2',
            'type' => 'video',
        ]);

        $this->assertDatabaseHas('materials', [
            'subject_id' => $subject->id,
            'title' => 'Materi Auto 2',
            'sort_order' => 2,
        ]);
    }

    public function test_admin_can_reorder_materials()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-05',
            'title' => 'Training 5',
            'created_by' => $admin->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subject 5',
            'sort_order' => 1,
        ]);

        $m1 = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Materi A',
            'type' => 'pdf',
            'sort_order' => 1,
            'created_by' => $admin->id,
        ]);

        $m2 = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Materi B',
            'type' => 'pdf',
            'sort_order' => 2,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.subjects.materials.reorder', $subject), [
            'orders' => [
                ['id' => $m1->id, 'order' => 2],
                ['id' => $m2->id, 'order' => 1],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertSame(2, $m1->fresh()->sort_order);
        $this->assertSame(1, $m2->fresh()->sort_order);
    }
}
