<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a user and assign one of the seeded roles.
 */
function actorWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/**
 * A small, valid WGS84 polygon covering part of the Abbay basin area.
 */
function geojsonBoundary(): string
{
    return json_encode([
        'type' => 'Feature',
        'properties' => ['name' => 'Test boundary'],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[
                [36.40, 10.20],
                [36.75, 10.20],
                [36.75, 10.55],
                [36.40, 10.55],
                [36.40, 10.20],
            ]],
        ],
    ], JSON_THROW_ON_ERROR);
}

/**
 * Same shape but the ring is not closed.
 */
function geojsonBoundaryUnclosed(): string
{
    return json_encode([
        'type' => 'Polygon',
        'coordinates' => [[
            [36.40, 10.20],
            [36.75, 10.20],
            [36.75, 10.55],
            [36.40, 10.55],
        ]],
    ], JSON_THROW_ON_ERROR);
}

/**
 * Basin and station used by the reporting suites.
 *
 * @return array{0: \App\Models\Basin, 1: \App\Models\MonitoringStation}
 */
function reportFixture(string $stationCode = 'ST-001'): array
{
    $basin = \App\Models\Basin::factory()->create([
        'code' => 'TST',
        'name' => 'Test basin',
        'area_km2' => 1200.5,
        'centroid_lat' => 9.1,
        'centroid_lng' => 40.2,
    ]);

    $station = \App\Models\MonitoringStation::create([
        'code' => $stationCode,
        'name' => 'Test station',
        'basin_id' => $basin->id,
        'station_type' => 'rainfall',
        'latitude' => 9.05,
        'longitude' => 40.15,
        'elevation_m' => 1850,
        'data_status' => 'verified',
        'is_active' => true,
    ]);

    return [$basin, $station];
}

function reportObserve(int $stationId, int $parameterId, string $measuredAt, float $value, string $quality = 'approved'): void
{
    \App\Models\HydrologicalObservation::create([
        'monitoring_station_id' => $stationId,
        'parameter_id' => $parameterId,
        'measured_at' => $measuredAt,
        'value' => $value,
        'quality_status' => $quality,
        'record_type' => 'observed',
    ]);
}
