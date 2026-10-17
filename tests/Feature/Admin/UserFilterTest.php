<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'pengembang']);
        Role::firstOrCreate(['name' => 'reviewer']);

        $this->admin = User::factory()->create(['name' => 'Admin Utama']);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_users_with_pagination(): void
    {
        // Total users = 1 admin + 14 generated = 15 users
        User::factory()->count(14)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 10)
            ->where('users.total', 15)
            ->has('users.links')
        );

        // Page 2
        $responsePage2 = $this->actingAs($this->admin)->get(route('admin.users.index', ['page' => 2]));
        $responsePage2->assertOk();
        $responsePage2->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 5)
        );
    }

    public function test_admin_can_filter_users_by_name(): void
    {
        $user1 = User::factory()->create(['name' => 'Bambang Pamungkas']);
        $user2 = User::factory()->create(['name' => 'Budi Sudarsono']);
        $user3 = User::factory()->create(['name' => 'Firman Utina']);

        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['name' => 'Bambang']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Bambang Pamungkas')
            ->where('filters.name', 'Bambang')
        );
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $dev1 = User::factory()->create(['name' => 'Dev Satu']);
        $dev1->assignRole('pengembang');

        $dev2 = User::factory()->create(['name' => 'Dev Dua']);
        $dev2->assignRole('pengembang');

        $rev1 = User::factory()->create(['name' => 'Reviewer Satu']);
        $rev1->assignRole('reviewer');

        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'pengembang']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
            ->where('filters.role', 'pengembang')
        );
    }

    public function test_admin_can_filter_users_by_both_name_and_role(): void
    {
        $dev = User::factory()->create(['name' => 'Siti Developer']);
        $dev->assignRole('pengembang');

        $rev = User::factory()->create(['name' => 'Siti Reviewer']);
        $rev->assignRole('reviewer');

        $response = $this->actingAs($this->admin)->get(route('admin.users.index', [
            'name' => 'Siti',
            'role' => 'reviewer',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Siti Reviewer')
        );
    }
}
