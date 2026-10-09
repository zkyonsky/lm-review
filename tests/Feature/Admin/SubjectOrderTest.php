<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubjectOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_order_auto_increments_when_created()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-01',
            'title' => 'Training 1',
            'created_by' => $admin->id,
        ]);

        // First subject without explicit order -> should be 1
        $this->actingAs($admin)->post(route('admin.trainings.subjects.store', $training), [
            'title' => 'Mata Pelatihan 1',
        ]);

        $this->assertDatabaseHas('subjects', [
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan 1',
            'sort_order' => 1,
        ]);

        // Second subject without explicit order -> should be 2
        $this->actingAs($admin)->post(route('admin.trainings.subjects.store', $training), [
            'title' => 'Mata Pelatihan 2',
        ]);

        $this->assertDatabaseHas('subjects', [
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan 2',
            'sort_order' => 2,
        ]);
    }

    public function test_admin_can_reorder_subjects()
    {
        Role::create(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $training = Training::create([
            'code' => 'TR-02',
            'title' => 'Training 2',
            'created_by' => $admin->id,
        ]);

        $subject1 = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subjek A',
            'sort_order' => 1,
        ]);

        $subject2 = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subjek B',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.trainings.subjects.reorder', $training), [
            'orders' => [
                ['id' => $subject1->id, 'order' => 2],
                ['id' => $subject2->id, 'order' => 1],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertSame(2, $subject1->fresh()->sort_order);
        $this->assertSame(1, $subject2->fresh()->sort_order);
    }

    public function test_developer_can_reorder_materials()
    {
        Role::create(['name' => 'developer']);
        Role::create(['name' => 'admin']);

        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $training = Training::create([
            'code' => 'TR-03',
            'title' => 'Training 3',
            'created_by' => $developer->id,
        ]);

        $subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Subjek C',
            'sort_order' => 1,
        ]);

        $subject->developers()->attach($developer->id, ['role' => 'pengembang']);

        $material1 = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Materi 1',
            'type' => 'pdf',
            'sort_order' => 1,
            'created_by' => $developer->id,
        ]);

        $material2 = Material::create([
            'subject_id' => $subject->id,
            'title' => 'Materi 2',
            'type' => 'video',
            'sort_order' => 2,
            'created_by' => $developer->id,
        ]);

        $response = $this->actingAs($developer)->post(route('developer.subjects.materials.reorder', $subject), [
            'orders' => [
                ['id' => $material1->id, 'order' => 2],
                ['id' => $material2->id, 'order' => 1],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertSame(2, $material1->fresh()->sort_order);
        $this->assertSame(1, $material2->fresh()->sort_order);
    }
}
