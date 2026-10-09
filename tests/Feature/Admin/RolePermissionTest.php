<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->developer = User::factory()->create();
        $this->developer->assignRole('pengembang');

        $this->reviewer = User::factory()->create();
        $this->reviewer->assignRole('reviewer');
    }

    public function test_admin_can_access_roles_permissions_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.roles-permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/permissions/index')
            ->has('roles')
            ->has('groupedPermissions')
        );
    }

    public function test_non_admin_cannot_access_roles_permissions_index(): void
    {
        // Developer cannot access
        $responseDeveloper = $this->actingAs($this->developer)->get(route('admin.roles-permissions.index'));
        $responseDeveloper->assertForbidden();

        // Reviewer cannot access
        $responseReviewer = $this->actingAs($this->reviewer)->get(route('admin.roles-permissions.index'));
        $responseReviewer->assertForbidden();

        // Guest is redirected
        auth()->logout();
        $responseGuest = $this->get(route('admin.roles-permissions.index'));
        $responseGuest->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        // Developer cannot access
        $responseDeveloper = $this->actingAs($this->developer)->get(route('admin.users.index'));
        $responseDeveloper->assertForbidden();

        // Reviewer cannot access
        $responseReviewer = $this->actingAs($this->reviewer)->get(route('admin.users.index'));
        $responseReviewer->assertForbidden();
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $reviewerRole = Role::findByName('reviewer');

        $newPermissions = ['view-trainings', 'view-materials', 'view-reviews'];

        $response = $this->actingAs($this->admin)->put(route('admin.roles-permissions.update', $reviewerRole), [
            'permissions' => $newPermissions,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $reviewerRole->refresh();
        $this->assertEqualsCanonicalizing($newPermissions, $reviewerRole->permissions->pluck('name')->toArray());
    }

    public function test_admin_can_create_new_custom_permission(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.permissions.store'), [
            'name' => 'view-system-audit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', [
            'name' => 'view-system-audit',
        ]);
    }

    public function test_non_admin_cannot_update_role_permissions(): void
    {
        $reviewerRole = Role::findByName('reviewer');

        $response = $this->actingAs($this->developer)->put(route('admin.roles-permissions.update', $reviewerRole), [
            'permissions' => ['view-trainings'],
        ]);

        $response->assertForbidden();
    }
}
