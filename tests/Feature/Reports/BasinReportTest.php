<?php

use App\Models\Parameter;
use App\Models\SubBasin;
use App\Services\Reports\ReportQueryService;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
});

it('describes a basin with its stations, parameters and coverage', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    SubBasin::create([
        'basin_id' => $basin->id,
        'code' => 'TST-1',
        'name' => 'Upper catchment',
        'area_km2' => 400,
        'data_status' => 'provisional',
    ]);

    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 5.0);
    reportObserve($station->id, $rainfall->id, '2020-01-02 09:00:00', 7.5);

    $report = app(ReportQueryService::class)->basin($basin);

    expect($report['basin']['code'])->toBe('TST')
        ->and($report['counts']['sub_basins'])->toBe(1)
        ->and($report['counts']['stations'])->toBe(1)
        ->and($report['counts']['parameters'])->toBe(1)
        ->and($report['sub_basins'][0]['name'])->toBe('Upper catchment')
        ->and($report['stations'][0]['observations'])->toBe(2)
        ->and($report['parameters'][0]['records'])->toBe(2)
        ->and($report['parameters'][0]['missing_percent'])->toBe(0.0)
        ->and($report['coverage']['records'])->toBe(2)
        ->and($report['quality']['approved'])->toBe(2)
        ->and($report['geography']['has_boundary'])->toBeFalse();
});

it('renders the basin report page for a user who can view reports', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-02-01 09:00:00', 11.0);

    $user = actorWithRole('Viewer');

    $this->actingAs($user)
        ->get("/reports/basins/{$basin->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Basin')
            ->where('report.basin.code', 'TST')
            ->has('report.stations', 1)
            ->has('report.parameters', 1)
            ->has('trend.points')
            ->has('abilities.export'));
});

it('renders the station report page with coverage and quality', function () {
    [, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-03-01 09:00:00', 3.0, 'flagged');

    $user = actorWithRole('Viewer');

    $this->actingAs($user)
        ->get("/reports/stations/{$station->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Station')
            ->where('report.station.code', 'ST-001')
            ->where('report.quality.flagged', 1)
            ->has('report.recent', 1));
});
