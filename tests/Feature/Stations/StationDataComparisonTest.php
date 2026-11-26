<?php

use App\Models\DataImportBatch;
use App\Models\MonitoringStation;
use App\Services\Stations\StationDataComparisonService;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);

    Storage::fake('local');

    $this->station = MonitoringStation::create([
        'code' => 'MET-0001',
        'name' => 'Gondar A.P',
        'station_type' => 'meteorological',
        'station_class' => 'synoptic',
        'latitude' => 12.5173345,
        'longitude' => 37.4258531,
        'data_status' => 'provisional',
        'is_active' => true,
    ]);
});

/**
 * @param  array<string, float|null>  $values
 */
function comparisonBatch(MonitoringStation $station, string $structure, array $values, string $uuid = null): DataImportBatch
{
    $uuid ??= (string) Str::uuid();
    $path = 'excel/'.$uuid.'/transformed.ndjson';
    $lines = [];

    foreach ($values as $date => $value) {
        $lines[] = json_encode([
            'row_number' => count($lines) + 2,
            'station_name' => $station->name,
            'parameter_code' => 'rainfall',
            'parameter' => 'Rainfall',
            'date' => $date,
            'time' => '09:00',
            'value' => $value,
            'status' => $value === null ? 'missing' : 'valid',
        ]);
    }

    Storage::disk('local')->put($path, implode(PHP_EOL, $lines).PHP_EOL);

    return DataImportBatch::create([
        'uuid' => $uuid,
        'file_name' => $structure.'.xlsx',
        'file_path' => 'excel/'.$uuid.'/source.xlsx',
        'monitoring_station_id' => $station->id,
        'status' => 'completed',
        'stage' => 'imported',
        'structure_type' => $structure,
        'sheet_name' => 'Sheet1',
        'mapping' => ['parameter_code' => 'rainfall'],
        'transformed_path' => $path,
        'imported_at' => now(),
    ]);
}

test('row-based and column-based uploads are compared by calendar date', function () {
    $rowBased = comparisonBatch($this->station, 'monthly_daily_columns', [
        '2020-01-01' => 0.0,
        '2020-01-02' => 12.5,
        '2020-01-03' => 4.0,
        '2020-02-01' => 8.0,
    ]);

    $columnBased = comparisonBatch($this->station, 'multi_header_matrix', [
        '2020-01-01' => 0.0,
        '2020-01-02' => 12.5,
        '2020-01-03' => 6.5,
        '2020-02-02' => 9.0,
    ]);

    $comparison = app(StationDataComparisonService::class)->compare($rowBased, $columnBased);

    expect($comparison['summary']['matching'])->toBe(2)
        ->and($comparison['summary']['different'])->toBe(1)
        ->and($comparison['summary']['only_left'])->toBe(1)
        ->and($comparison['summary']['only_right'])->toBe(1)
        ->and($comparison['summary']['max_difference'])->toBe(2.5)
        ->and($comparison['summary']['agreement_pct'])->toBe(66.7)
        ->and(collect($comparison['differences'])->firstWhere('date', '2020-01-03')['difference'])->toBe(-2.5);
});

test('the comparison page lists only this station uploads', function () {
    $rowBased = comparisonBatch($this->station, 'monthly_daily_columns', ['2020-01-01' => 1.0]);
    $columnBased = comparisonBatch($this->station, 'multi_header_matrix', ['2020-01-01' => 1.0]);

    $otherStation = MonitoringStation::create([
        'code' => 'MET-0002',
        'name' => 'Other station',
        'station_type' => 'rainfall',
        'latitude' => 9.0,
        'longitude' => 38.7,
        'data_status' => 'provisional',
        'is_active' => true,
    ]);

    $foreign = comparisonBatch($otherStation, 'daily_series', ['2020-01-01' => 99.0]);

    $manager = actorWithRole('Data Manager');

    $this->actingAs($manager)
        ->get('/stations/'.$this->station->id.'/compare?left='.$rowBased->uuid.'&right='.$columnBased->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Compare')
            ->has('batches', 2)
            ->where('comparison.summary.matching', 1)
            ->where('comparison.left.uuid', $rowBased->uuid)
        );

    $this->actingAs($manager)
        ->get('/stations/'.$this->station->id.'/compare?left='.$rowBased->uuid.'&right='.$foreign->uuid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches', 2)
            ->where('comparison', null)
        );
});
