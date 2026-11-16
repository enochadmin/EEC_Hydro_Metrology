<?php

use App\Models\ActivityLog;
use App\Models\Basin;
use App\Models\BasinBoundary;
use App\Models\Document;
use App\Models\SubBasin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists basins for a viewer with summary counters', function () {
    Basin::factory()->count(3)->create();

    $this->actingAs(actorWithRole('Viewer'))
        ->get('/basins')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Basins/Index')
            ->has('basins.data', 3)
            ->where('summary.total', 3)
            ->where('summary.boundary_pending', 3)
            ->where('filters.sort', 'code')
        );
});

it('searches, filters and sorts the basin registry', function () {
    Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    Basin::factory()->create(['code' => 'AW-02', 'name' => 'Awash']);

    $this->actingAs(actorWithRole('Viewer'))
        ->get('/basins?search=ABB&sort=name&direction=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('basins.data', 1)
            ->where('basins.data.0.code', 'BH-01')
        );

    $this->actingAs(actorWithRole('Viewer'))
        ->get('/basins?status=pending&state=active')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('basins.data', 2));
});

it('blocks viewers from creating or editing basins', function () {
    $basin = Basin::factory()->create();
    $viewer = actorWithRole('Viewer');

    $this->actingAs($viewer)->get('/basins/create')->assertForbidden();
    $this->actingAs($viewer)->post('/basins', ['code' => 'BH-99', 'name' => 'Nope', 'data_status' => 'pending'])->assertForbidden();
    $this->actingAs($viewer)->get("/basins/{$basin->id}/edit")->assertForbidden();
    $this->actingAs($viewer)->delete("/basins/{$basin->id}")->assertForbidden();
});

it('lets a basin manager register a basin and logs the action', function () {
    $response = $this->actingAs(actorWithRole('Basin Manager'))->post('/basins', [
        'code' => 'bh-50',
        'name' => 'Test Basin',
        'name_alt' => 'Alternative',
        'description' => 'Registered during the test suite.',
        'area_km2' => 1250.75,
        'centroid_lat' => 9.145,
        'centroid_lng' => 40.489673,
        'elevation_m' => 1450,
        'data_status' => 'provisional',
        'is_active' => true,
    ]);

    $basin = Basin::firstOrFail();

    $response->assertRedirect(route('basins.show', $basin));
    expect($basin->code)->toBe('BH-50')
        ->and($basin->name)->toBe('Test Basin')
        ->and((float) $basin->area_km2)->toBe(1250.75);

    expect(ActivityLog::where('event', 'basin.created')->where('subject_id', $basin->id)->exists())->toBeTrue();
});

it('rejects duplicate codes even when the original basin is archived', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01']);
    $basin->delete();

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post('/basins', ['code' => 'bh-01', 'name' => 'Duplicate', 'data_status' => 'pending'])
        ->assertSessionHasErrors('code');

    expect(Basin::withTrashed()->where('code', 'BH-01')->count())->toBe(1);
});

it('requires confirmed geography before a basin can be verified', function () {
    $this->actingAs(actorWithRole('Basin Manager'))
        ->post('/basins', ['code' => 'BH-77', 'name' => 'Unverified', 'data_status' => 'verified'])
        ->assertSessionHasErrors(['area_km2', 'centroid_lat', 'centroid_lng']);

    expect(Basin::where('code', 'BH-77')->exists())->toBeFalse();
});

it('rejects coordinates outside the wgs84 range', function () {
    $this->actingAs(actorWithRole('Basin Manager'))
        ->post('/basins', [
            'code' => 'BH-78',
            'name' => 'Out of range',
            'data_status' => 'provisional',
            'centroid_lat' => 95,
            'centroid_lng' => 40,
        ])
        ->assertSessionHasErrors('centroid_lat');
});

it('updates a basin without tripping over its own unique code', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    $manager = actorWithRole('Basin Manager');

    $this->actingAs($manager)
        ->patch("/basins/{$basin->id}", [
            'code' => 'bh-01',
            'name' => 'Abbay (Blue Nile)',
            'data_status' => 'provisional',
            'area_km2' => 201812.5,
            'centroid_lat' => 9.145,
            'centroid_lng' => 40.489673,
            'is_active' => true,
        ])
        ->assertRedirect(route('basins.show', $basin));

    $basin->refresh();

    expect($basin->name)->toBe('Abbay (Blue Nile)')
        ->and($basin->code)->toBe('BH-01')
        ->and(ActivityLog::where('event', 'basin.updated')->exists())->toBeTrue();
});

it('refuses to delete a basin that still has geographic children', function () {
    $basin = Basin::factory()->create();
    SubBasin::factory()->create(['basin_id' => $basin->id]);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->delete("/basins/{$basin->id}")
        ->assertSessionHasErrors('basin');

    $this->assertNotSoftDeleted('basins', ['id' => $basin->id]);
});

it('soft deletes an empty basin', function () {
    $basin = Basin::factory()->create();
    $manager = actorWithRole('Basin Manager');

    $this->actingAs($manager)
        ->delete("/basins/{$basin->id}")
        ->assertRedirect(route('basins.index'));

    $this->assertSoftDeleted('basins', ['id' => $basin->id]);
    expect(ActivityLog::where('event', 'basin.deleted')->exists())->toBeTrue();
});

it('exports the filtered registry as csv', function () {
    Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    Basin::factory()->create(['code' => 'AW-02', 'name' => 'Awash']);

    $response = $this->actingAs(actorWithRole('Viewer'))
        ->get('/basins/export?search=BH-01');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('BH-01')
        ->and($csv)->toContain('Sub-basins')
        ->and($csv)->not->toContain('AW-02');
});

it('imports a geojson boundary with srid 4326 and mirrors the display geometry', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01']);

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post(route('basins.boundary.store', $basin), [
            'name' => 'EEC master boundary 2026',
            'is_primary' => true,
            'file' => UploadedFile::fake()->createWithContent('abbay.geojson', geojsonBoundary()),
        ])
        ->assertRedirect(route('basins.show', $basin));

    $boundary = BasinBoundary::where('basin_id', $basin->id)->firstOrFail();

    expect($boundary->is_primary)->toBeTrue()
        ->and($boundary->srid)->toBe(4326);

    $stored = DB::table('basin_boundaries')
        ->where('id', $boundary->id)
        ->selectRaw('ST_SRID(geom) as srid, ST_GeometryType(geom) as type')
        ->first();

    expect((int) $stored->srid)->toBe(4326)
        ->and(strtoupper($stored->type))->toContain('POLYGON');

    $display = DB::table('basins')->where('id', $basin->id)->selectRaw('ST_SRID(boundary) as srid')->first();
    expect((int) $display->srid)->toBe(4326);

    expect(ActivityLog::where('event', 'basin.boundary.imported')->exists())->toBeTrue();
});

it('rejects an unclosed geojson ring with a readable error', function () {
    $basin = Basin::factory()->create();

    $this->actingAs(actorWithRole('Basin Manager'))
        ->post(route('basins.boundary.store', $basin), [
            'name' => 'Broken',
            'file' => UploadedFile::fake()->createWithContent('broken.geojson', geojsonBoundaryUnclosed()),
        ])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('basin_boundaries', 0);
});

it('allows a gis officer to import a boundary without registry edit rights', function () {
    $basin = Basin::factory()->create();
    $gis = actorWithRole('GIS Officer');

    $this->actingAs($gis)
        ->post(route('basins.boundary.store', $basin), [
            'name' => 'GIS polygon',
            'file' => UploadedFile::fake()->createWithContent('gis.geojson', geojsonBoundary()),
        ])
        ->assertRedirect(route('basins.show', $basin));

    $this->actingAs($gis)->post('/basins', [
        'code' => 'BH-88',
        'name' => 'Should fail',
        'data_status' => 'pending',
    ])->assertForbidden();

    $this->actingAs(actorWithRole('Viewer'))
        ->post(route('basins.boundary.store', $basin), [
            'name' => 'Blocked',
            'file' => UploadedFile::fake()->createWithContent('blocked.geojson', geojsonBoundary()),
        ])
        ->assertForbidden();
});

it('stores, downloads and removes basin documents', function () {
    Storage::fake('local');

    $basin = Basin::factory()->create();
    $manager = actorWithRole('Basin Manager');

    $this->actingAs($manager)
        ->post(route('basins.documents.store', $basin), [
            'title' => 'Gazette notice',
            'file' => UploadedFile::fake()->create('gazette.pdf', 32, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = Document::where('documentable_id', $basin->id)->firstOrFail();

    Storage::disk('local')->assertExists($document->file_path);

    $this->actingAs($manager)
        ->get(route('basins.documents.download', [$basin, $document]))
        ->assertOk();

    $this->actingAs($manager)
        ->delete(route('basins.documents.destroy', [$basin, $document]))
        ->assertRedirect();

    $this->assertSoftDeleted('documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($document->file_path);
});

it('does not serve a document through the wrong basin', function () {
    Storage::fake('local');

    $basin = Basin::factory()->create();
    $other = Basin::factory()->create();
    $manager = actorWithRole('Basin Manager');

    $this->actingAs($manager)->post(route('basins.documents.store', $basin), [
        'title' => 'Survey report',
        'file' => UploadedFile::fake()->create('survey.pdf', 12, 'application/pdf'),
    ]);

    $document = Document::firstOrFail();

    $this->actingAs($manager)
        ->get(route('basins.documents.download', [$other, $document]))
        ->assertNotFound();
});

it('shows the basin detail page with related geography and tabs data', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01', 'name' => 'Abbay']);
    SubBasin::factory()->create(['basin_id' => $basin->id, 'code' => 'SUB-01', 'name' => 'Didessa']);

    $this->actingAs(actorWithRole('Viewer'))
        ->get(route('basins.show', $basin))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Basins/Show')
            ->where('basin.code', 'BH-01')
            ->has('subBasins', 1)
            ->where('subBasins.0.code', 'SUB-01')
            ->has('stations.data', 0)
            ->has('boundaries', 0)
            ->has('documents', 0)
            ->where('abilities.importBoundary', false)
        );
});
