<?php

use App\Models\Parameter;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
});

it('redirects guests to the login screen', function () {
    $this->get('/reports')->assertRedirect('/login');
});

it('rejects an authenticated user without the view reports permission', function () {
    $user = actorWithRole('GIS Officer');

    $this->actingAs($user)->get('/reports')->assertForbidden();
});

it('lets a viewer open the reports landing page', function () {
    $user = actorWithRole('Viewer');

    $this->actingAs($user)
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports/Index')->has('overview.basins')->has('abilities'));
});

it('refuses a download when the user cannot export reports', function () {
    [$basin] = reportFixture();
    $user = actorWithRole('Viewer');

    $this->actingAs($user)
        ->get("/reports/basins/{$basin->id}/export/csv")
        ->assertForbidden();
});

it('restricts a report that carries provisional observations', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2021-03-02 09:00:00', 8.5, 'pending');

    $user = actorWithRole('Viewer');
    $user->givePermissionTo('export reports');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user)
        ->get("/reports/basins/{$basin->id}/export/csv")
        ->assertForbidden();
});

it('allows the restricted export for a user holding the restricted permission', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2021-03-02 09:00:00', 8.5, 'pending');

    $user = actorWithRole('Data Analyst');

    $this->actingAs($user)
        ->get("/reports/basins/{$basin->id}/export/csv")
        ->assertOk();
});
