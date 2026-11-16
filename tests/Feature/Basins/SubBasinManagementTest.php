<?php

use App\Models\Basin;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists sub-basins with their parent basin', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    SubBasin::factory()->create(['basin_id' => $basin->id, 'code' => 'SUB-01', 'name' => 'Didessa']);

    $this->actingAs(actorWithRole('Viewer'))
        ->get('/sub-basins')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SubBasins/Index')
            ->has('subBasins.data', 1)
            ->where('subBasins.data.0.code', 'SUB-01')
            ->where('subBasins.data.0.basin.code', 'BH-01')
            ->where('summary.pending', 1)
        );
});

it('filters sub-basins by parent basin', function () {
    $abbay = Basin::factory()->create(['code' => 'BH-01']);
    $awash = Basin::factory()->create(['code' => 'AW-02']);
    SubBasin::factory()->create(['basin_id' => $abbay->id, 'code' => 'SUB-01']);
    SubBasin::factory()->create(['basin_id' => $awash->id, 'code' => 'SUB-02']);

    $this->actingAs(actorWithRole('Viewer'))
        ->get("/sub-basins?basin_id={$awash->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subBasins.data', 1)
            ->where('subBasins.data.0.code', 'SUB-02')
        );
});

it('blocks viewers and data managers from creating sub-basins', function () {
    $basin = Basin::factory()->create();

    $payload = [
        'basin_id' => $basin->id,
        'code' => 'SUB-99',
        'name' => 'Not allowed',
        'data_status' => 'pending',
    ];

    $this->actingAs(actorWithRole('Viewer'))->post('/sub-basins', $payload)->assertForbidden();
    $this->actingAs(actorWithRole('Data Manager'))->post('/sub-basins', $payload)->assertForbidden();
});

it('registers a sub-basin under its parent basin', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01']);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post('/sub-basins', [
            'basin_id' => $basin->id,
            'code' => 'sub-01',
            'name' => 'Didessa',
            'name_alt' => 'Dedessa',
            'description' => 'Tributary of the Abbay.',
            'area_km2' => 12500.25,
            'centroid_lat' => 8.95,
            'centroid_lng' => 36.45,
            'elevation_m' => 1250,
            'data_status' => 'provisional',
            'is_active' => true,
        ])
        ->assertRedirect();

    $subBasin = SubBasin::firstOrFail();

    expect($subBasin->code)->toBe('SUB-01')
        ->and($subBasin->basin_id)->toBe($basin->id)
        ->and((float) $subBasin->area_km2)->toBe(12500.25);
});

it('rejects a parent basin that has been archived', function () {
    $basin = Basin::factory()->create();
    $basin->delete();

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post('/sub-basins', [
            'basin_id' => $basin->id,
            'code' => 'SUB-02',
            'name' => 'Orphan',
            'data_status' => 'pending',
        ])
        ->assertSessionHasErrors('basin_id');

    $this->assertDatabaseCount('sub_basins', 0);
});

it('rejects a duplicate sub-basin code', function () {
    SubBasin::factory()->create(['code' => 'SUB-01']);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post('/sub-basins', [
            'basin_id' => Basin::factory()->create()->id,
            'code' => 'sub-01',
            'name' => 'Duplicate',
            'data_status' => 'pending',
        ])
        ->assertSessionHasErrors('code');
});

it('moves a sub-basin to another parent basin', function () {
    $abbay = Basin::factory()->create(['code' => 'BH-01']);
    $awash = Basin::factory()->create(['code' => 'AW-02']);
    $subBasin = SubBasin::factory()->create(['basin_id' => $abbay->id, 'code' => 'SUB-01', 'name' => 'Didessa']);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->patch("/sub-basins/{$subBasin->id}", [
            'basin_id' => $awash->id,
            'code' => 'SUB-01',
            'name' => 'Didessa',
            'data_status' => 'pending',
            'is_active' => true,
        ])
        ->assertRedirect(route('sub-basins.show', $subBasin));

    expect($subBasin->refresh()->basin_id)->toBe($awash->id);
});

it('refuses to delete a sub-basin that still has stations', function () {
    $subBasin = SubBasin::factory()->create();

    MonitoringStation::create([
        'code' => 'ST-01',
        'name' => 'Didessa gauge',
        'basin_id' => $subBasin->basin_id,
        'sub_basin_id' => $subBasin->id,
        'station_type' => 'river_gauge',
    ]);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->delete("/sub-basins/{$subBasin->id}")
        ->assertSessionHasErrors('sub-basin');

    $this->assertNotSoftDeleted('sub_basins', ['id' => $subBasin->id]);
});

it('soft deletes an empty sub-basin', function () {
    $subBasin = SubBasin::factory()->create();

    $this->actingAs(actorWithRole('Basin Manager'))
        ->delete("/sub-basins/{$subBasin->id}")
        ->assertRedirect(route('sub-basins.index'));

    $this->assertSoftDeleted('sub_basins', ['id' => $subBasin->id]);
});

it('imports a sub-basin boundary with srid 4326', function () {
    $subBasin = SubBasin::factory()->create(['code' => 'SUB-01']);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post(route('sub-basins.boundary.store', $subBasin), [
            'name' => 'Didessa catchment',
            'file' => UploadedFile::fake()->createWithContent('didessa.geojson', geojsonBoundary()),
        ])
        ->assertRedirect(route('sub-basins.show', $subBasin));

    $stored = DB::table('sub_basins')
        ->where('id', $subBasin->id)
        ->selectRaw('ST_SRID(boundary) as srid, ST_GeometryType(boundary) as type')
        ->first();

    expect((int) $stored->srid)->toBe(4326)
        ->and(strtoupper($stored->type))->toContain('POLYGON');
});

it('shows stations and boundary status on the sub-basin page', function () {
    $subBasin = SubBasin::factory()->create(['code' => 'SUB-01', 'name' => 'Didessa']);

    MonitoringStation::create([
        'code' => 'ST-01',
        'name' => 'Didessa gauge',
        'basin_id' => $subBasin->basin_id,
        'sub_basin_id' => $subBasin->id,
        'station_type' => 'river_gauge',
    ]);

    $this->actingAs(actorWithRole('Viewer'))
        ->get(route('sub-basins.show', $subBasin))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SubBasins/Show')
            ->where('subBasin.code', 'SUB-01')
            ->where('subBasin.boundary_status', 'pending')
            ->has('stations.data', 1)
            ->where('stations.data.0.code', 'ST-01')
            ->where('summary.stations', 1)
        );
});

it('exports sub-basins as csv', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    SubBasin::factory()->create(['basin_id' => $basin->id, 'code' => 'SUB-01', 'name' => 'Didessa']);

    $response = $this->actingAs(actorWithRole('Viewer'))
        ->get('/sub-basins/export');

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('SUB-01')
        ->and($csv)->toContain('Abbay');
});
