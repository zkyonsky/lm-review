<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::findOrCreate('admin');
        \Spatie\Permission\Models\Role::findOrCreate('pengembang');
        \Spatie\Permission\Models\Role::findOrCreate('reviewer');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_reviewer_can_visit_the_dashboard()
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        $response = $this->actingAs($reviewer)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_developer_can_visit_the_dashboard()
    {
        $developer = User::factory()->create();
        $developer->assignRole('pengembang');

        $response = $this->actingAs($developer)->get(route('dashboard'));
        $response->assertOk();
    }
}
