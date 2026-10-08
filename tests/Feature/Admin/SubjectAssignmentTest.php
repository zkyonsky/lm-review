<?php

namespace Tests\Feature\Admin;

use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $reviewer;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'pengembang']);
        Role::create(['name' => 'reviewer']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->developer = User::factory()->create(['name' => 'Pengembang 1']);
        $this->developer->assignRole('pengembang');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer 1']);
        $this->reviewer->assignRole('reviewer');

        $training = Training::create([
            'code' => 'TR-01',
            'title' => 'Pelatihan Dasar',
            'created_by' => $this->admin->id,
        ]);

        $this->subject = Subject::create([
            'training_id' => $training->id,
            'title' => 'Mata Pelatihan 1',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_assign_developer_to_subject()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->developer->id,
            'role' => 'pengembang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subject_user', [
            'subject_id' => $this->subject->id,
            'user_id' => $this->developer->id,
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $this->assertTrue($this->subject->developers->contains($this->developer));
    }

    public function test_admin_can_assign_reviewer_to_subject()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->reviewer->id,
            'role' => 'reviewer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subject_user', [
            'subject_id' => $this->subject->id,
            'user_id' => $this->reviewer->id,
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        $this->assertTrue($this->subject->reviewers->contains($this->reviewer));
    }

    public function test_cannot_assign_user_without_developer_role_as_developer()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->reviewer->id,
            'role' => 'pengembang',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('subject_user', [
            'subject_id' => $this->subject->id,
            'user_id' => $this->reviewer->id,
            'role' => 'pengembang',
        ]);
    }

    public function test_cannot_assign_user_without_reviewer_role_as_reviewer()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->developer->id,
            'role' => 'reviewer',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('subject_user', [
            'subject_id' => $this->subject->id,
            'user_id' => $this->developer->id,
            'role' => 'reviewer',
        ]);
    }

    public function test_cannot_assign_duplicate_user_for_same_role()
    {
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->developer->id,
            'role' => 'pengembang',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_admin_can_unassign_user_from_subject()
    {
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.subjects.unassign', [$this->subject, $this->developer]) . '?role=pengembang'
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('subject_user', [
            'subject_id' => $this->subject->id,
            'user_id' => $this->developer->id,
            'role' => 'pengembang',
        ]);
    }

    public function test_subject_show_page_loads_with_assignments_data()
    {
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);

        $this->subject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.show', $this->subject));

        $response->assertOk();
    }

    public function test_assigning_reviewer_to_subject_creates_review_and_review_assignment_for_existing_materials()
    {
        $material = $this->subject->materials()->create([
            'title' => 'Materi Uji',
            'type' => 'pdf',
            'created_by' => $this->admin->id,
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);
        $material->update(['current_version_id' => $version->id]);

        $response = $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->reviewer->id,
            'role' => 'reviewer',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('review_assignments', [
            'material_id' => $material->id,
            'reviewer_id' => $this->reviewer->id,
        ]);

        $this->assertDatabaseHas('reviews', [
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
            'status' => 'draft',
        ]);
    }

    public function test_unassigning_reviewer_from_subject_cleans_up_draft_reviews()
    {
        $material = $this->subject->materials()->create([
            'title' => 'Materi Uji',
            'type' => 'pdf',
            'created_by' => $this->admin->id,
        ]);

        $version = $material->versions()->create([
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);
        $material->update(['current_version_id' => $version->id]);

        // Assign
        $this->actingAs($this->admin)->post(route('admin.subjects.assign', $this->subject), [
            'user_id' => $this->reviewer->id,
            'role' => 'reviewer',
        ]);

        $this->assertDatabaseHas('reviews', [
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
        ]);

        // Unassign
        $response = $this->actingAs($this->admin)->delete(
            route('admin.subjects.unassign', [$this->subject, $this->reviewer]) . '?role=reviewer'
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('reviews', [
            'material_version_id' => $version->id,
            'reviewer_id' => $this->reviewer->id,
        ]);
    }
}
