<?php

use App\Models\Parameter;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
});

it('renders the analysis page with a default parameter and result', function () {
    [, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 6.0);
    reportObserve($station->id, $rainfall->id, '2020-01-02 09:00:00', 4.0);

    $user = actorWithRole('Data Analyst');

    $this->actingAs($user)
        ->get('/reports/analysis')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Analysis')
            ->where('result.series.metric', 'total')
            ->where('result.series.points.0.value', 10)
            ->has('result.stations')
            ->has('result.basins')
            ->has('options.parameters'));
});

it('returns the same aggregation as json for the filters', function () {
    [, $station] = reportFixture();
    $temperature = Parameter::where('code', 'temperature')->firstOrFail();

    reportObserve($station->id, $temperature->id, '2020-06-01 09:00:00', 18.0);
    reportObserve($station->id, $temperature->id, '2020-06-02 09:00:00', 22.0);

    $user = actorWithRole('Data Analyst');

    $this->actingAs($user)
        ->getJson("/reports/analysis/data?parameter_id={$temperature->id}&granularity=monthly")
        ->assertOk()
        ->assertJsonPath('series.metric', 'mean')
        ->assertJsonPath('series.points.0.value', 20)
        ->assertJsonPath('series.profile.accumulates', false);
});

it('refuses provisional records for a user who cannot generate reports', function () {
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    $user = actorWithRole('Viewer');

    $this->actingAs($user)
        ->getJson("/reports/analysis/data?parameter_id={$rainfall->id}&quality=all")
        ->assertStatus(422);
});

it('downloads an analysis export for the selected filters', function () {
    [, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 6.0);

    $user = actorWithRole('Data Analyst');

    $response = $this->actingAs($user)
        ->get("/reports/analysis/export/csv?parameter_id={$rainfall->id}&granularity=monthly");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.csv');
});
