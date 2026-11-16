<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $user->assignRole('Viewer');

        $this->actingAs($user);

        $this->get('/dashboard')->assertOk();
    }

    public function test_accounts_without_a_role_are_denied_the_dashboard()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertForbidden();
    }
}
