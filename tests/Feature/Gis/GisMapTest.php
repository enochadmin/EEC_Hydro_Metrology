<?php

use App\Models\Basin;
use App\Models\GeoFeature;
use App\Models\HydrologicalObservation;
use App\Models\MeasurementUnit;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\SubBasin;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function gisPolygonJson(float $offset = 0.0): string
{
    $x = 36.0 + $offset;

    return json_encode([
        'type' => 'Feature',
        'properties' => ['name' => 'Test polygon'],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[
                [$x, 9.0],
                [$x + 0.8, 9.0],
                [$x + 0.8, 9.8],
                [$x, 9.8],
                [$x, 9.0],
            ]],
        ],
    ], JSON_THROW_ON_ERROR);
}

function gisLineJson(): string
{
    return json_encode([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['name' => 'Test river'],
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[36.0, 9.0], [36.5, 9.4], [37.0, 9.6]],
            ],
        ]],
    ], JSON_THROW_ON_ERROR);
}

it('opens the map page with layer availability and station types', function () {
    $this->actingAs(actorWithRole('Viewer'))
        ->get('/gis')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Gis/Index')
            ->has('layers', 10)
            ->has('station_types', 7)
            ->has('basin_legend', 0)
            ->where('abilities.import', false)
            ->where('has_geometry', false)
        );

    $this->actingAs(actorWithRole('GIS Officer'))
        ->get('/gis')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('abilities.import', true));
});

it('denies the map to users without a role', function () {
    $this->actingAs(User::factory()->create())
        ->get('/gis')
        ->assertForbidden();
});

it('serves basin polygons as a 4326 feature collection', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    $basin->setGeometryWkt('boundary', 'POLYGON((36 9, 37 9, 37 10, 36 9))');

    $response = $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/basins');

    $response->assertOk()
        ->assertJsonPath('type', 'FeatureCollection')
        ->assertJsonPath('features.0.properties.code', 'BH-01')
        ->assertJsonPath('features.0.properties.color', config('gis.basin_palette')[0])
        ->assertJsonPath('features.0.geometry.type', 'Polygon');

    expect($response->json('features.0.geometry.coordinates.0'))->toHaveCount(4);
});

it('returns sub-basins and station summaries when a basin is opened', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    $basin->setGeometryWkt('boundary', 'POLYGON((36 9, 37 9, 37 10, 36 9))');

    $subBasin = SubBasin::factory()->create(['basin_id' => $basin->id, 'code' => 'SUB-01', 'name' => 'Didessa']);
    $subBasin->setGeometryWkt('boundary', 'POLYGON((36 9, 36.5 9, 36.5 9.5, 36 9))');

    MonitoringStation::create([
        'code' => 'ST-01',
        'name' => 'Didessa gauge',
        'basin_id' => $basin->id,
        'sub_basin_id' => $subBasin->id,
        'latitude' => 9.2,
        'longitude' => 36.2,
        'station_type' => 'river_gauge',
    ]);

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson("/gis/data/basins/{$basin->id}")
        ->assertOk()
        ->assertJsonPath('basin.code', 'BH-01')
        ->assertJsonPath('basin.sub_basins_count', 1)
        ->assertJsonPath('basin.stations_count', 1)
        ->assertJsonPath('sub_basins.features.0.properties.code', 'SUB-01')
        ->assertJsonPath('stations.features.0.properties.code', 'ST-01')
        ->assertJsonPath('stations.features.0.geometry.coordinates.1', 9.2);
});

it('filters station points by type and returns station details', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01']);

    foreach ([['ST-01', 'river_gauge'], ['ST-02', 'rainfall']] as [$code, $type]) {
        MonitoringStation::create([
            'code' => $code,
            'name' => 'Station '.$code,
            'basin_id' => $basin->id,
            'latitude' => 9.1,
            'longitude' => 40.1,
            'station_type' => $type,
        ]);
    }

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/stations?types[]=rainfall')
        ->assertOk()
        ->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.code', 'ST-02');

    $station = MonitoringStation::where('code', 'ST-01')->firstOrFail();

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson("/gis/data/stations/{$station->id}")
        ->assertOk()
        ->assertJsonPath('station.code', 'ST-01')
        ->assertJsonPath('station.type_label', 'Streamflow / river gauge')
        ->assertJsonPath('station.basin.code', 'BH-01')
        ->assertJsonPath('availability.records', 0)
        ->assertJsonPath('parameters', []);
});

it('searches basins, sub-basins and stations', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    SubBasin::factory()->create(['basin_id' => $basin->id, 'code' => 'SUB-01', 'name' => 'Didessa']);
    MonitoringStation::create([
        'code' => 'ST-01',
        'name' => 'Abbay gauge',
        'basin_id' => $basin->id,
        'latitude' => 9.1,
        'longitude' => 40.1,
        'station_type' => 'river_gauge',
    ]);

    $response = $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/search?q=Abbay')
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type')->all();

    expect($types)->toContain('basin')
        ->and($types)->toContain('station');
});

it('imports a national boundary with srid 4326 and exposes it as a layer', function () {
    $this->actingAs(actorWithRole('GIS Officer'))
        ->post(route('gis.features.store'), [
            'layer_type' => 'national_boundary',
            'name' => 'Ethiopia national boundary',
            'file' => UploadedFile::fake()->createWithContent('ethiopia.geojson', gisPolygonJson()),
        ])
        ->assertRedirect();

    $feature = GeoFeature::firstOrFail();

    expect($feature->layer_type)->toBe('national_boundary')
        ->and($feature->geometry_type)->toBe('Polygon');

    $srid = DB::table('geo_features')->where('id', $feature->id)->selectRaw('ST_SRID(geom) as srid')->first();
    expect((int) $srid->srid)->toBe(4326);

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/features/national_boundary')
        ->assertOk()
        ->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.name', 'Ethiopia national boundary');
});

it('imports river lines and rejects polygons on the river layer', function () {
    $officer = actorWithRole('GIS Officer');

    $this->actingAs($officer)
        ->post(route('gis.features.store'), [
            'layer_type' => 'river',
            'file' => UploadedFile::fake()->createWithContent('river.geojson', gisLineJson()),
        ])
        ->assertRedirect();

    expect(GeoFeature::where('layer_type', 'river')->count())->toBe(1);

    $this->actingAs($officer)
        ->post(route('gis.features.store'), [
            'layer_type' => 'lake',
            'file' => UploadedFile::fake()->createWithContent('river-as-lake.geojson', gisLineJson()),
        ])
        ->assertSessionHasErrors('file');
});

it('rejects projected coordinates and a projected crs declaration', function () {
    $officer = actorWithRole('GIS Officer');

    $projected = json_encode([
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[[500000, 900000], [500100, 900000], [500100, 900100], [500000, 900000]]],
        ],
        'properties' => [],
    ], JSON_THROW_ON_ERROR);

    $this->actingAs($officer)
        ->post(route('gis.features.store'), [
            'layer_type' => 'lake',
            'file' => UploadedFile::fake()->createWithContent('projected.geojson', $projected),
        ])
        ->assertSessionHasErrors('file');

    $declared = json_encode([
        'type' => 'FeatureCollection',
        'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:EPSG::3857']],
        'features' => [[
            'type' => 'Feature',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[36, 9], [37, 9], [37, 10], [36, 9]]]],
            'properties' => [],
        ]],
    ], JSON_THROW_ON_ERROR);

    $this->actingAs($officer)
        ->post(route('gis.features.store'), [
            'layer_type' => 'lake',
            'file' => UploadedFile::fake()->createWithContent('declared-3857.geojson', $declared),
        ])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('geo_features', 0);
});

it('blocks duplicate geometry in the same layer', function () {
    $officer = actorWithRole('GIS Officer');

    $payload = [
        'layer_type' => 'lake',
        'file' => UploadedFile::fake()->createWithContent('lake.geojson', gisPolygonJson()),
    ];

    $this->actingAs($officer)->post(route('gis.features.store'), $payload)->assertRedirect();

    $this->actingAs($officer)
        ->post(route('gis.features.store'), [
            'layer_type' => 'lake',
            'name' => 'Duplicate lake',
            'file' => UploadedFile::fake()->createWithContent('lake-copy.geojson', gisPolygonJson()),
        ])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('geo_features', 1);
});

it('only lets gis officers manage geographic layers', function () {
    $payload = [
        'layer_type' => 'lake',
        'file' => UploadedFile::fake()->createWithContent('lake.geojson', gisPolygonJson()),
    ];

    $this->actingAs(actorWithRole('Viewer'))->post(route('gis.features.store'), $payload)->assertForbidden();
    $this->actingAs(actorWithRole('Basin Manager'))->post(route('gis.features.store'), $payload)->assertForbidden();
});

it('removes an imported feature with an audit entry', function () {
    $officer = actorWithRole('GIS Officer');

    $this->actingAs($officer)->post(route('gis.features.store'), [
        'layer_type' => 'lake',
        'name' => 'Lake Tana',
        'file' => UploadedFile::fake()->createWithContent('tana.geojson', gisPolygonJson()),
    ]);

    $feature = GeoFeature::firstOrFail();

    $this->actingAs($officer)
        ->delete(route('gis.features.destroy', $feature))
        ->assertRedirect();

    $this->assertSoftDeleted('geo_features', ['id' => $feature->id]);
    $this->assertDatabaseHas('activity_logs', ['event' => 'gis.feature.deleted']);
});

it('imports a multi-feature lake collection', function () {
    $collection = json_encode([
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'properties' => ['name' => 'Lake one'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[36.0, 9.0], [36.4, 9.0], [36.4, 9.4], [36.0, 9.0]]]],
            ],
            [
                'type' => 'Feature',
                'properties' => ['name' => 'Lake two'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[37.0, 9.0], [37.4, 9.0], [37.4, 9.4], [37.0, 9.0]]]],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->actingAs(actorWithRole('GIS Officer'))
        ->post(route('gis.features.store'), [
            'layer_type' => 'lake',
            'file' => UploadedFile::fake()->createWithContent('lakes.geojson', $collection),
        ])
        ->assertRedirect();

    expect(GeoFeature::where('layer_type', 'lake')->count())->toBe(2);

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/features/lakes')
        ->assertOk()
        ->assertJsonCount(2, 'features');
});

it('rejects an unclosed polygon ring', function () {
    $unclosed = json_encode([
        'type' => 'Feature',
        'properties' => [],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[[36.0, 9.0], [36.5, 9.0], [36.5, 9.5], [36.0, 9.5]]],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->actingAs(actorWithRole('GIS Officer'))
        ->post(route('gis.features.store'), [
            'layer_type' => 'lake',
            'file' => UploadedFile::fake()->createWithContent('unclosed.geojson', $unclosed),
        ])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('geo_features', 0);
});

it('keeps a single active national boundary when a new one is imported', function () {
    $officer = actorWithRole('GIS Officer');

    $this->actingAs($officer)->post(route('gis.features.store'), [
        'layer_type' => 'national_boundary',
        'name' => 'Boundary 2025',
        'file' => UploadedFile::fake()->createWithContent('boundary-2025.geojson', gisPolygonJson()),
    ]);

    $this->actingAs($officer)->post(route('gis.features.store'), [
        'layer_type' => 'national_boundary',
        'name' => 'Boundary 2026',
        'file' => UploadedFile::fake()->createWithContent('boundary-2026.geojson', gisPolygonJson(2.0)),
    ]);

    expect(GeoFeature::where('layer_type', 'national_boundary')->count())->toBe(2)
        ->and(GeoFeature::where('layer_type', 'national_boundary')->where('is_active', true)->count())->toBe(1);

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/features/national_boundary')
        ->assertOk()
        ->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.name', 'Boundary 2026');
});

it('requires a search term of at least two characters', function () {
    $this->actingAs(actorWithRole('Viewer'))
        ->getJson('/gis/data/search?q=a')
        ->assertStatus(422);
});

it('reports configured parameters and the observation period for a station', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01']);

    $station = MonitoringStation::create([
        'code' => 'ST-01',
        'name' => 'Abbay gauge',
        'basin_id' => $basin->id,
        'latitude' => 9.1,
        'longitude' => 40.1,
        'station_type' => 'river_gauge',
    ]);

    $unit = MeasurementUnit::create([
        'code' => 'm3s',
        'name' => 'Cubic metres per second',
        'symbol' => 'm³/s',
        'dimension' => 'volumetric_flow',
    ]);

    $parameter = Parameter::create([
        'code' => 'streamflow',
        'name' => 'Streamflow',
        'category' => 'hydrological',
        'default_unit_id' => $unit->id,
    ]);

    $station->stationParameters()->create([
        'parameter_id' => $parameter->id,
        'measurement_unit_id' => $unit->id,
        'is_active' => true,
    ]);

    HydrologicalObservation::create([
        'monitoring_station_id' => $station->id,
        'parameter_id' => $parameter->id,
        'measurement_unit_id' => $unit->id,
        'measured_at' => '2024-01-02 09:00:00',
        'value' => 12.345,
        'quality_status' => 'approved',
    ]);

    $this->actingAs(actorWithRole('Viewer'))
        ->getJson("/gis/data/stations/{$station->id}")
        ->assertOk()
        ->assertJsonPath('parameters.0.name', 'Streamflow')
        ->assertJsonPath('parameters.0.unit', 'm³/s')
        ->assertJsonPath('availability.records', 1)
        ->assertJsonPath('availability.first_at', '2024-01-02 09:00:00');
});
