<?php

use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\StationImportBatch;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);

    Storage::fake('local');

    $this->manager = actorWithRole('Data Manager');
});

/**
 * A station list workbook in the shape circulated by the met network:
 * projected metres under "Lat"/"Lon" headers, mixed class spellings.
 *
 * @param  list<list<mixed>>  $rows
 */
function stationImportWorkbook(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
        ['No.', 'NAME', 'Lat', 'Lon', 'Class'],
        ...$rows,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'stations').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'Met stations.xlsx', null, null, true);
}

test('a projected station list is converted, classified and staged without touching the registry', function () {
    $file = stationImportWorkbook([
        [1, 'Gondar A.P', 328953.886081, 1384269.89518, 'Synoptic Met Station'],
        [2, 'Estayish', 516047.901394, 1308136.93307, 'Precipitation Met Station'],
        [3, 'Debre Tabor', 391896.611387, 1311112.62072, ' Principal Met Station'],
        [4, 'Belesa(Hamusit)', 361034.56318, 1360376.95832, 'ordinary met station'],
    ]);

    $response = $this->actingAs($this->manager)->post('/stations/import', [
        'file' => $file,
        'code_prefix' => 'MET',
        'utm_zone' => 37,
        'coordinates' => 'auto',
        'data_status' => 'provisional',
        'parameters' => ['rainfall', 'temperature'],
    ]);

    $batch = StationImportBatch::sole();

    $response->assertRedirect('/stations/import/'.$batch->uuid);

    expect($batch->status)->toBe('previewed')
        ->and($batch->total_rows)->toBe(4)
        ->and($batch->importable_rows)->toBe(4)
        ->and($batch->summary['coordinates'])->toBe('projected')
        ->and($batch->summary['classes'])->toMatchArray([
            'synoptic' => 1,
            'precipitation' => 1,
            'principal' => 1,
            'ordinary' => 1,
        ])
        ->and($batch->mapping['detected']['name'])->toBe('NAME');

    expect(MonitoringStation::query()->count())->toBe(0);
});

test('committing the preview creates stations with WGS84 coordinates, class and parameters', function () {
    $file = stationImportWorkbook([
        [1, 'Gondar A.P', 328953.886081, 1384269.89518, 'Synoptic Met Station'],
        [2, 'Estayish', 516047.901394, 1308136.93307, 'percipitation met station'],
    ]);

    $this->actingAs($this->manager)->post('/stations/import', [
        'file' => $file,
        'code_prefix' => 'MET',
        'coordinates' => 'auto',
        'parameters' => ['rainfall', 'temperature'],
    ])->assertRedirect();

    $batch = StationImportBatch::sole();

    $this->actingAs($this->manager)->post('/stations/import/'.$batch->uuid.'/commit', [
        'code_prefix' => 'MET',
        'coordinates' => 'auto',
        'parameters' => ['rainfall', 'temperature'],
    ])->assertRedirect('/stations');

    $gondar = MonitoringStation::query()->where('name', 'Gondar A.P')->sole();

    expect($gondar->code)->toBe('MET-0001')
        ->and($gondar->station_class)->toBe('synoptic')
        ->and($gondar->station_type)->toBe('meteorological')
        ->and($gondar->basin_id)->toBeNull()
        ->and((float) $gondar->latitude)->toBeGreaterThan(12.4)
        ->and((float) $gondar->latitude)->toBeLessThan(12.6)
        ->and((float) $gondar->longitude)->toBeGreaterThan(37.3)
        ->and((float) $gondar->longitude)->toBeLessThan(37.5)
        ->and($gondar->observations()->count())->toBe(0);

    $estayish = MonitoringStation::query()->where('name', 'Estayish')->sole();

    expect($estayish->station_class)->toBe('precipitation')
        ->and($estayish->station_type)->toBe('rainfall')
        ->and($estayish->stationParameters()->count())->toBe(2);

    expect(Parameter::query()->whereIn('code', ['rainfall', 'temperature'])->count())->toBe(2)
        ->and($batch->fresh()->status)->toBe('imported')
        ->and($batch->fresh()->imported_rows)->toBe(2);
});

test('a station already registered at the same place is reported as a duplicate and not imported', function () {
    MonitoringStation::create([
        'code' => 'ST-EXISTING',
        'name' => 'Gondar A.P',
        'station_type' => 'meteorological',
        'latitude' => 12.5173345,
        'longitude' => 37.4258531,
        'data_status' => 'verified',
        'is_active' => true,
    ]);

    $file = stationImportWorkbook([
        [1, 'Gondar A.P', 328953.886081, 1384269.89518, 'Synoptic Met Station'],
        [2, 'Estayish', 516047.901394, 1308136.93307, 'Precipitation Met Station'],
    ]);

    $this->actingAs($this->manager)->post('/stations/import', [
        'file' => $file,
        'coordinates' => 'auto',
        'parameters' => ['rainfall'],
    ]);

    $batch = StationImportBatch::sole();

    expect($batch->summary['duplicates'])->toBe(1)
        ->and($batch->summary['ready'])->toBe(1);

    $this->actingAs($this->manager)->post('/stations/import/'.$batch->uuid.'/commit', [
        'coordinates' => 'auto',
        'parameters' => ['rainfall'],
    ]);

    expect(MonitoringStation::query()->count())->toBe(2)
        ->and(MonitoringStation::query()->where('code', 'ST-EXISTING')->exists())->toBeTrue();
});

test('the station import screen and the preview page are permission guarded', function () {
    $viewer = actorWithRole('Viewer');

    $this->actingAs($viewer)->get('/stations/import')->assertForbidden();

    $batch = StationImportBatch::create([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'file_name' => 'stations.xlsx',
        'status' => 'previewed',
        'stage' => 'previewed',
    ]);

    $this->actingAs($viewer)->get('/stations/import/'.$batch->uuid)->assertForbidden();

    $this->actingAs($this->manager)->get('/stations/import')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Stations/Import')
        ->has('classes')
        ->has('parameters')
    );
});

test('decimal degree files are accepted without conversion', function () {
    $file = stationImportWorkbook([
        [1, 'Addis Ababa Observatory', 9.0333, 38.75, 'Principal Met Station'],
    ]);

    $this->actingAs($this->manager)->post('/stations/import', [
        'file' => $file,
        'coordinates' => 'auto',
        'parameters' => ['rainfall'],
    ]);

    $batch = StationImportBatch::sole();
    $row = $batch->summary;

    expect($row['coordinates'])->toBe('geographic')
        ->and($row['latitude_range'][0])->toEqualWithDelta(9.0333, 0.001)
        ->and($row['longitude_range'][0])->toEqualWithDelta(38.75, 0.001);
});
