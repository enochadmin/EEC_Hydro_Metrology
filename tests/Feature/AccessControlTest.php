<?php

use App\Models\User;
use App\Services\Access\UserAccountService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeUserWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('seeds the seven roles and the full permission catalogue', function () {
    expect(Role::count())->toBe(7)
        ->and(Permission::count())->toBe(34)
        ->and(Role::findByName('Super Admin')->permissions()->count())->toBe(34);
});

it('gives the viewer read-only access only', function () {
    $permissions = Role::findByName('Viewer')->permissions->pluck('name')->all();

    expect($permissions)
        ->toContain('view dashboard', 'view basins', 'view sub-basins', 'view map', 'view stations', 'view data', 'view reports')
        ->not->toContain('create basins', 'manage permissions', 'create users', 'upload data', 'approve data');
});

it('restricts the system administrator to access management', function () {
    $permissions = Role::findByName('System Administrator')->permissions->pluck('name')->all();

    expect($permissions)
        ->toContain('manage permissions', 'assign roles', 'deactivate users', 'manage settings')
        ->not->toContain('upload data', 'approve data', 'manage map layers');
});

it('allows a super admin to open user management', function () {
    $this->actingAs(makeUserWithRole('Super Admin'))
        ->get('/users')
        ->assertOk();
});

it('blocks a viewer from user management', function () {
    $this->actingAs(makeUserWithRole('Viewer'))
        ->get('/users')
        ->assertForbidden();
});

it('blocks a basin manager from role management', function () {
    $this->actingAs(makeUserWithRole('Basin Manager'))
        ->get('/access-control/roles')
        ->assertForbidden();
});

it('shares roles and permissions with the frontend', function () {
    $user = makeUserWithRole('Data Manager');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', ['Data Manager'])
            ->has('auth.user.permissions', 15)
            ->where('auth.user.is_super_admin', false)
        );
});

it('refuses sign-in for deactivated accounts', function () {
    $user = makeUserWithRole('Viewer', ['is_active' => false]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('ends an existing session when the account is deactivated', function () {
    $user = makeUserWithRole('Viewer');

    $this->actingAs($user);

    $user->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('prevents an administrator from deactivating their own account', function () {
    $admin = makeUserWithRole('System Administrator');

    $this->actingAs($admin)
        ->patch("/users/{$admin->id}/status", ['is_active' => false])
        ->assertForbidden();
});

it('creates a user with an assigned role', function () {
    $admin = makeUserWithRole('System Administrator');

    $this->actingAs($admin)->post('/users', [
        'name' => 'New Analyst',
        'email' => 'analyst@eec-bhdms.local',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'roles' => ['Data Analyst'],
    ])->assertRedirect('/users');

    $created = User::query()->where('email', 'analyst@eec-bhdms.local')->first();

    expect($created)->not->toBeNull()
        ->and($created->hasRole('Data Analyst'))->toBeTrue()
        ->and($created->isActive())->toBeTrue();
});

it('does not let a non super admin grant the super admin role', function () {
    $admin = makeUserWithRole('System Administrator');

    $this->actingAs($admin)->post('/users', [
        'name' => 'Escalation Attempt',
        'email' => 'escalation@eec-bhdms.local',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'roles' => ['Super Admin'],
    ])->assertSessionHasErrors('roles');

    expect(User::query()->where('email', 'escalation@eec-bhdms.local')->exists())->toBeFalse();
});

it('updates the permissions granted to a role', function () {
    $admin = makeUserWithRole('Super Admin');
    $viewer = Role::findByName('Viewer');

    $this->actingAs($admin)
        ->put("/access-control/roles/{$viewer->id}", [
            'permissions' => ['view dashboard', 'view basins'],
        ])
        ->assertRedirect('/access-control/roles');

    expect(Role::findByName('Viewer')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['view dashboard', 'view basins']);
});

it('locks the super admin role against permission editing', function () {
    $admin = makeUserWithRole('Super Admin');
    $superAdminRole = Role::findByName('Super Admin');

    $this->actingAs($admin)
        ->put("/access-control/roles/{$superAdminRole->id}", [
            'permissions' => ['view dashboard'],
        ])
        ->assertForbidden();
});

it('keeps at least one active super admin', function () {
    $superAdmin = makeUserWithRole('Super Admin');
    $secondAdmin = makeUserWithRole('Super Admin');
    $secondAdmin->forceFill(['is_active' => false])->save();

    $actor = makeUserWithRole('System Administrator');

    expect(fn () => app(UserAccountService::class)->setActive($superAdmin, false, $actor))
        ->toThrow(ValidationException::class);
});

it('authenticates api requests with sanctum tokens', function () {
    $user = makeUserWithRole('Data Analyst');
    $token = $user->createToken('verification')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.roles.0', 'Data Analyst');
});

it('rejects unauthenticated api requests', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
