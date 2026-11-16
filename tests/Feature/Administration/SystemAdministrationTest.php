<?php

use App\Models\ActivityLog;
use App\Models\MeasurementUnit;
use App\Models\Parameter;
use App\Models\StationCategory;
use App\Models\User;
use App\Services\Settings\SystemSettingService;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StationCategorySeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
    $this->seed(StationCategorySeeder::class);
});

it('records a login event without exposing the credential', function () {
    $user = actorWithRole('Viewer');

    event(new Login('web', $user, false));

    $entry = ActivityLog::where('event', 'auth.login')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($user->id)
        ->and(json_encode($entry->properties))->not->toContain('password');
});

it('lets an administrator save settings and applies them to config', function () {
    $admin = actorWithRole('System Administrator');

    $this->actingAs($admin)
        ->put('/settings/system', [
            'settings' => [
                'app_name' => 'EEC Hydro',
                'map_zoom' => 7,
            ],
        ])
        ->assertRedirect();

    app(SystemSettingService::class)->forget();
    app(SystemSettingService::class)->apply();

    expect(config('app.name'))->toBe('EEC Hydro')
        ->and(config('eec.map.zoom'))->toBe(7)
        ->and(ActivityLog::where('event', 'settings.updated')->count())->toBeGreaterThan(0);
});

it('rejects settings changes from a user without the permission', function () {
    $user = actorWithRole('Viewer');

    $this->actingAs($user)
        ->put('/settings/system', ['settings' => ['app_name' => 'Nope']])
        ->assertForbidden();
});

it('refuses to delete a parameter that has observations', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 3.0);

    $admin = actorWithRole('System Administrator');

    $this->actingAs($admin)
        ->delete("/settings/system/parameters/{$rainfall->id}")
        ->assertSessionHasErrors('parameter');

    expect(Parameter::whereKey($rainfall->id)->exists())->toBeTrue();
});

it('refuses to delete a unit that is still referenced', function () {
    $unit = MeasurementUnit::where('code', 'mm')->firstOrFail();
    $admin = actorWithRole('System Administrator');

    $this->actingAs($admin)
        ->delete("/settings/system/units/{$unit->id}")
        ->assertSessionHasErrors('unit');
});

it('creates a role with the selected permissions', function () {
    $admin = actorWithRole('System Administrator');

    $this->actingAs($admin)
        ->post('/access-control/roles', [
            'name' => 'Field supervisor',
            'description' => 'Reads stations and reports.',
            'permissions' => ['view dashboard', 'view reports'],
        ])
        ->assertRedirect('/access-control/roles');

    $role = Role::where('name', 'Field supervisor')->firstOrFail();

    expect($role->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['view dashboard', 'view reports'])
        ->and(ActivityLog::where('event', 'role.created')->exists())->toBeTrue();
});

it('refuses to delete a role that still has members', function () {
    $admin = actorWithRole('System Administrator');
    actorWithRole('Viewer');
    $role = Role::where('name', 'Viewer')->firstOrFail();

    $this->actingAs($admin)
        ->delete("/access-control/roles/{$role->id}")
        ->assertSessionHasErrors('role');

    expect(Role::whereKey($role->id)->exists())->toBeTrue();
});

it('protects the Super Admin role from modification', function () {
    $admin = actorWithRole('System Administrator');
    $super = Role::where('name', config('access.super_admin_role'))->firstOrFail();

    $this->actingAs($admin)
        ->delete("/access-control/roles/{$super->id}")
        ->assertSessionHasErrors('role');

    expect(Role::whereKey($super->id)->exists())->toBeTrue();
});

it('resets a password and never stores it in the audit trail', function () {
    $admin = actorWithRole('System Administrator');
    $target = User::factory()->create(['password' => Hash::make('original-password')]);

    $this->actingAs($admin)
        ->post("/users/{$target->id}/password", [
            'password' => 'N3w-Secure-Password!',
            'password_confirmation' => 'N3w-Secure-Password!',
        ])
        ->assertRedirect();

    expect(Hash::check('N3w-Secure-Password!', $target->fresh()->password))->toBeTrue();

    $entry = ActivityLog::where('event', 'user.password_reset')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and(json_encode($entry->properties))->not->toContain('N3w-Secure-Password!');
});

it('shows an account with its activity to an authorised administrator', function () {
    $admin = actorWithRole('System Administrator');
    $target = User::factory()->create();

    ActivityLog::create([
        'user_id' => $target->id,
        'event' => 'auth.login',
        'description' => 'signed in',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get("/users/{$target->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Show')
            ->where('account.id', $target->id)
            ->has('activity', 1));
});

it('keeps the activity log away from unauthorised users', function () {
    $viewer = actorWithRole('Viewer');

    $this->actingAs($viewer)->get('/activity-logs')->assertForbidden();

    $admin = actorWithRole('System Administrator');

    $this->actingAs($admin)
        ->get('/activity-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Administration/ActivityLogs/Index'));
});

it('seeds station categories matched to the station types', function () {
    expect(StationCategory::count())->toBe(7)
        ->and(StationCategory::where('code', 'rainfall')->value('label'))->toBe('Rainfall');
});
