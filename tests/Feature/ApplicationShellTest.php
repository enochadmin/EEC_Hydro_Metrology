<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function viewerUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('Viewer');

    return $user;
}

it('renders the dashboard with shared application context', function () {
    $user = viewerUser();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.roles', ['Viewer'])
            ->has('auth.user.permissions', 7)
            ->where('auth.user.is_super_admin', false)
            ->has('stats', 4)
            ->has('modules', 8)
            ->has('systemStatus.items', 5)
            ->where('basinTarget', 12)
            ->where('application.short_name', 'EEC-BHDMS')
            ->where('application.version', config('eec.app.version'))
        );
});

it('leaves headline indicators empty until their modules are delivered', function () {
    $this->actingAs(viewerUser())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.0.key', 'basins')
            ->where('stats.0.value', null)
            ->where('stats.3.key', 'records')
            ->where('stats.3.value', null)
        );
});

it('renders the settings pages inside the application shell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Profile'));

    $this->actingAs($user)
        ->get('/settings/password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Password'));

    $this->actingAs($user)
        ->get('/settings/appearance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Appearance'));
});

it('protects the application shell from guests', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/settings/profile')->assertRedirect('/login');
    $this->get('/users')->assertRedirect('/login');
});
