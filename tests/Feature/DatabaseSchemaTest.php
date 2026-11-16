<?php

use App\Models\ActivityLog;
use App\Models\Basin;
use App\Models\BasinBoundary;
use App\Models\DataQualityIssue;
use App\Models\HydrologicalObservation;
use App\Models\MeasurementUnit;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\SubBasin;
use Database\Seeders\BasinSeeder;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
    $this->seed(BasinSeeder::class);
});

function schemaStation(array $attributes = []): MonitoringStation
{
    return MonitoringStation::query()->create(array_merge([
        'code' => 'ST-001',
        'name' => 'Test gauge',
        'basin_id' => Basin::query()->value('id'),
        'station_type' => 'river_gauge',
    ], $attributes));
}

it('creates every phase 3 table', function () {
    $tables = [
        'data_sources',
        'measurement_units',
        'basins',
        'sub_basins',
        'watersheds',
        'basin_boundaries',
        'monitoring_stations',
        'parameters',
        'station_parameters',
        'data_import_batches',
        'hydrological_observations',
        'data_import_errors',
        'data_quality_issues',
        'activity_logs',
        'basin_user_assignments',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

it('seeds twelve basins without inventing geographic data', function () {
    expect(Basin::query()->count())->toBe(12)
        ->and(Basin::query()->where('data_status', 'verified')->count())->toBe(0)
        ->and(Basin::query()->whereNotNull('area_km2')->count())->toBe(0)
        ->and(Basin::query()->whereNotNull('centroid_lat')->count())->toBe(0)
        ->and(Basin::query()->whereNotNull('boundary')->count())->toBe(0);

    expect(Basin::query()->where('code', 'BH-01')->value('name'))->toBe('Abbay');
});

it('keeps the basin seeder idempotent', function () {
    $this->seed(BasinSeeder::class);

    expect(Basin::query()->count())->toBe(12);
});

it('enforces a unique basin code', function () {
    expect(fn () => Basin::query()->create(['code' => 'BH-01', 'name' => 'Duplicate basin']))
        ->toThrow(QueryException::class);
});

it('restricts deleting a basin that still has sub-basins', function () {
    $basin = Basin::query()->firstOrFail();

    SubBasin::query()->create([
        'basin_id' => $basin->id,
        'code' => 'SB-001',
        'name' => 'Test sub-basin',
    ]);

    expect(fn () => $basin->forceDelete())->toThrow(QueryException::class);
});

it('stores wgs84 geometry and answers a spatial containment query', function () {
    $basin = Basin::query()->firstOrFail();

    $wkt = 'POLYGON((38 8, 39 8, 39 9, 38 9, 38 8))';

    $boundary = BasinBoundary::createWithGeometry([
        'basin_id' => $basin->id,
        'name' => 'Synthetic test polygon',
        'is_primary' => true,
    ], $wkt);

    expect($boundary->fresh()->geometryWkt('geom'))->toContain('POLYGON');

    $probe = DB::selectOne(
        'select ST_SRID(geom) as srid, ST_Contains(geom, ST_GeomFromText(?, 4326)) as contains from basin_boundaries where id = ?',
        ['POINT(38.5 8.5)', $boundary->id],
    );

    expect((int) $probe->srid)->toBe(4326)
        ->and((int) $probe->contains)->toBe(1);

    $spatialIndex = DB::select("SHOW INDEX FROM basin_boundaries WHERE Index_type = 'SPATIAL'");

    expect($spatialIndex)->not->toBeEmpty();
});

it('blocks duplicate observations but allows a second reading on the same timestamp', function () {
    $station = schemaStation();
    $rainfall = Parameter::query()->where('code', 'rainfall')->firstOrFail();
    $unit = MeasurementUnit::query()->where('code', 'mm')->firstOrFail();

    $reading = [
        'monitoring_station_id' => $station->id,
        'parameter_id' => $rainfall->id,
        'measurement_unit_id' => $unit->id,
        'measured_at' => '2026-03-01 06:00:00',
        'value' => 12.4,
        'series_no' => 1,
    ];

    HydrologicalObservation::query()->create($reading);

    expect(fn () => HydrologicalObservation::query()->create($reading))
        ->toThrow(QueryException::class);

    $replicate = HydrologicalObservation::query()->create([...$reading, 'series_no' => 2, 'record_type' => 'observed']);

    expect($replicate->exists)->toBeTrue()
        ->and(HydrologicalObservation::query()->count())->toBe(2);
});

it('exposes the geographic and observational relationships', function () {
    $basin = Basin::query()->firstOrFail();

    $subBasin = SubBasin::query()->create([
        'basin_id' => $basin->id,
        'code' => 'SB-002',
        'name' => 'Relationship sub-basin',
    ]);

    $station = schemaStation([
        'code' => 'ST-REL',
        'sub_basin_id' => $subBasin->id,
    ]);

    $streamflow = Parameter::query()->where('code', 'streamflow')->firstOrFail();

    $observation = HydrologicalObservation::query()->create([
        'monitoring_station_id' => $station->id,
        'parameter_id' => $streamflow->id,
        'measurement_unit_id' => $streamflow->default_unit_id,
        'measured_at' => '2026-03-01 08:00:00',
        'value' => 41.275,
    ]);

    $issue = DataQualityIssue::query()->create([
        'hydrological_observation_id' => $observation->id,
        'monitoring_station_id' => $station->id,
        'parameter_id' => $streamflow->id,
        'issue_type' => 'range',
        'severity' => 'high',
        'description' => 'Synthetic check for the relationship test.',
    ]);

    expect($basin->subBasins()->count())->toBe(1)
        ->and($subBasin->basin->is($basin))->toBeTrue()
        ->and($station->observations()->count())->toBe(1)
        ->and($observation->parameter->code)->toBe('streamflow')
        ->and($observation->unit->code)->toBe('m3s')
        ->and($streamflow->defaultUnit->code)->toBe('m3s')
        ->and($issue->observation->is($observation))->toBeTrue()
        ->and($observation->qualityIssues()->count())->toBe(1);
});

it('links stations to parameters through the pivot table', function () {
    $station = schemaStation(['code' => 'ST-PIVOT']);
    $temperature = Parameter::query()->where('code', 'temperature')->firstOrFail();

    $station->parameters()->attach($temperature->id, [
        'measurement_unit_id' => $temperature->default_unit_id,
        'sensor_reference' => 'Sensor A',
        'is_active' => true,
    ]);

    expect($station->parameters()->count())->toBe(1)
        ->and($station->parameters->first()->pivot->sensor_reference)->toBe('Sensor A');
});

it('soft deletes geographic records without losing the row', function () {
    $basin = Basin::query()->firstOrFail();

    $basin->delete();

    expect(Basin::query()->count())->toBe(11)
        ->and(Basin::withTrashed()->count())->toBe(12)
        ->and(Basin::withTrashed()->find($basin->id)->trashed())->toBeTrue();
});

it('keeps an audit trail with a polymorphic subject and no updated_at', function () {
    $basin = Basin::query()->firstOrFail();

    $log = ActivityLog::query()->create([
        'event' => 'basin.created',
        'subject_type' => Basin::class,
        'subject_id' => $basin->id,
        'description' => 'Registry row created by the seeder.',
        'properties' => ['code' => $basin->code],
    ]);

    expect($log->subject->is($basin))->toBeTrue()
        ->and($log->properties)->toBeArray()
        ->and($log->updated_at)->toBeNull();
});
