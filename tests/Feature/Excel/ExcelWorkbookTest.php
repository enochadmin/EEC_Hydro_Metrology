<?php

use App\Models\Basin;
use App\Models\DataImportBatch;
use App\Models\HydrologicalObservation;
use App\Models\MonitoringStation;
use App\Models\User;
use App\Services\Excel\ExcelComparisonService;
use App\Services\Excel\ExcelExportService;
use App\Services\Excel\ExcelImportService;
use App\Services\Excel\ExcelStructureDetectionService;
use App\Services\Excel\ExcelTransformationService;
use App\Services\Excel\ExcelValidationService;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);

    Storage::fake('local');

    $uuid = (string) Str::uuid();
    Storage::disk('local')->makeDirectory('excel/'.$uuid);
    Storage::disk('local')->put(
        'excel/'.$uuid.'/source.xlsx',
        file_get_contents(base_path('tests/Fixtures/Rainfall Data.xlsx')),
    );

    $this->batch = DataImportBatch::create([
        'uuid' => $uuid,
        'file_name' => 'Rainfall Data.xlsx',
        'file_path' => 'excel/'.$uuid.'/source.xlsx',
        'file_size' => filesize(base_path('tests/Fixtures/Rainfall Data.xlsx')),
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'uploaded_by' => null,
        'status' => 'pending',
        'stage' => 'uploaded',
    ]);
});

/**
 * @return list<array<string, mixed>>
 */
function excelRows(DataImportBatch $batch): array
{
    $path = Storage::disk('local')->path((string) $batch->transformed_path);
    $rows = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $decoded = json_decode($line, true);

        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
    }

    return $rows;
}

it('detects both real worksheets and their structures', function () {
    $detector = app(ExcelStructureDetectionService::class);

    $first = $detector->detect($this->batch, 'Arba Minch Raw Data');

    expect(array_column($first['candidates'], 'key'))->toContain('monthly_daily_columns')
        ->and($first['candidates'][0]['day_column_count'])->toBe(31)
        ->and($first['candidates'][0]['columns']['station'])->toBe('A')
        ->and($first['candidates'][0]['columns']['year'])->toBe('F')
        ->and($first['candidates'][0]['columns']['month'])->toBe('G');

    $second = $detector->detect($this->batch, 'Arba Minch column');

    expect(array_column($second['candidates'], 'key'))->toContain('multi_header_matrix')
        ->and(array_column($second['candidates'], 'key'))->toContain('daily_series');

    $matrix = collect($second['candidates'])->firstWhere('key', 'multi_header_matrix');
    $daily = collect($second['candidates'])->firstWhere('key', 'daily_series');

    expect($matrix['period_count'])->toBe(600)
        ->and($matrix['day_column'])->toBe('C')
        ->and($daily['formula_based'])->toBeTrue()
        ->and($daily['value_column'])->toBe('B');
});

it('transforms the monthly sheet into one row per valid calendar date', function () {
    $detector = app(ExcelStructureDetectionService::class);
    $transformer = app(ExcelTransformationService::class);

    $candidate = collect($detector->detect($this->batch, 'Arba Minch Raw Data')['candidates'])
        ->firstWhere('key', 'monthly_daily_columns');

    $result = $transformer->transform($this->batch, 'Arba Minch Raw Data', 'monthly_daily_columns', [
        'header_row' => $candidate['header_row'],
        'data_start_row' => $candidate['data_start_row'],
        'columns' => $candidate['columns'],
        'day_columns' => $candidate['day_columns'],
        'parameter_code' => 'rainfall',
        'time' => '09:00',
    ]);

    expect($result['summary']['original_rows'])->toBe(433)
        ->and($result['summary']['original_columns'])->toBe(39)
        ->and($result['summary']['generated_observations'])->toBeGreaterThan(12000)
        ->and($result['summary']['zero_measurements'])->toBeGreaterThan(7000)
        ->and($result['summary']['missing_measurements'])->toBeGreaterThan(700)
        ->and($result['summary']['invalid_records'])->toBe(0);

    $rows = excelRows($this->batch);
    $byDate = collect($rows)->keyBy(fn (array $row): string => (string) ($row['date'] ?? '').'|'.($row['row_number'] ?? ''));

    $jan4 = collect($rows)->firstWhere('date', '1987-01-04');
    expect($jan4['value'])->toEqual(10.5)
        ->and($jan4['station_name'])->toBe('Arba Minch')
        ->and($jan4['status'])->toBe('valid');

    expect(collect($rows)->where('date', '1987-01-01')->first()['value'])->toEqual(0.0)
        ->and(collect($rows)->where('date', '1987-02-29')->count())->toBe(0)
        ->and(collect($rows)->where('status', 'missing')->count())->toBeGreaterThan(700);

    unset($byDate);
});

it('transforms the matrix sheet and compares it with the formula-driven date series', function () {
    $detector = app(ExcelStructureDetectionService::class);
    $transformer = app(ExcelTransformationService::class);

    $candidates = $detector->detect($this->batch, 'Arba Minch column')['candidates'];
    $matrix = collect($candidates)->firstWhere('key', 'multi_header_matrix');

    $mapping = [
        'header_rows' => $matrix['header_rows'],
        'year_row' => $matrix['year_row'],
        'month_row' => $matrix['month_row'],
        'month_name_row' => $matrix['month_name_row'],
        'day_column' => $matrix['day_column'],
        'day_rows' => $matrix['day_rows'],
        'periods' => $matrix['periods'],
        'station_name' => 'Arba Minch',
        'parameter_label' => 'Rainfall',
        'parameter_code' => 'rainfall',
        'time' => '09:00',
    ];

    $result = $transformer->transform($this->batch, 'Arba Minch column', 'multi_header_matrix', $mapping);

    expect($result['summary']['generated_observations'])->toBeGreaterThan(12000)
        ->and($result['summary']['valid_calendar_dates'])->toBe(18262)
        ->and($result['summary']['missing_measurements'])->toBeGreaterThan(5000);

    $rows = excelRows($this->batch);
    expect(collect($rows)->where('date', '2000-02-29')->count())->toBe(1)
        ->and(collect($rows)->where('date', '2001-02-29')->count())->toBe(0);

    $comparison = app(ExcelComparisonService::class)->compare($this->batch, 'Arba Minch column', $mapping);

    expect($comparison['available'])->toBeTrue()
        ->and($comparison['formula_mirror'])->toBeTrue()
        ->and($comparison['matching'])->toBeGreaterThan(12000)
        ->and($comparison['alignment_errors'])->toBeGreaterThan(0)
        ->and($comparison['daily']['formula_rows'])->toBe(18262);
});

it('validates, imports and exports the monthly dataset', function () {
    $detector = app(ExcelStructureDetectionService::class);
    $transformer = app(ExcelTransformationService::class);

    $candidate = collect($detector->detect($this->batch, 'Arba Minch Raw Data')['candidates'])
        ->firstWhere('key', 'monthly_daily_columns');

    $transformer->transform($this->batch, 'Arba Minch Raw Data', 'monthly_daily_columns', [
        'header_row' => $candidate['header_row'],
        'data_start_row' => $candidate['data_start_row'],
        'columns' => $candidate['columns'],
        'day_columns' => $candidate['day_columns'],
        'parameter_code' => 'rainfall',
        'time' => '09:00',
    ]);

    $validation = app(ExcelValidationService::class)->validate($this->batch);

    expect($validation['summary']['valid_rows'])->toBe(12413)
        ->and($validation['summary']['missing_rows'])->toBe(736)
        ->and($validation['summary']['duplicate_rows'])->toBe(31)
        ->and($validation['summary']['stations'])->toBe(1);

    $basin = Basin::factory()->create(['code' => 'BH-01']);
    $actor = User::factory()->create();

    $counts = app(ExcelImportService::class)->import($this->batch, [
        'Arba Minch' => [
            'mode' => 'create',
            'code' => 'ARB-01',
            'basin_id' => $basin->id,
        ],
    ], $actor);

    $station = MonitoringStation::where('code', 'ARB-01')->firstOrFail();

    // The repeated 2011-01 month holds missing cells only; valid rows stay unique.
    expect($counts['imported'])->toBe(12413)
        ->and($counts['duplicates'])->toBe(0)
        ->and((float) $station->latitude)->toBe(6.057167)
        ->and((float) $station->longitude)->toBe(37.55783);

    expect(HydrologicalObservation::count())->toBe($counts['imported']);

    $this->batch->refresh();
    expect($this->batch->stage)->toBe('imported')
        ->and($this->batch->imported_rows)->toBe(12413);

    $export = app(ExcelExportService::class)->transformedWorkbook($this->batch);
    expect(is_file($export['path']))->toBeTrue()
        ->and(filesize($export['path']))->toBeGreaterThan(10000);

    $errors = app(ExcelExportService::class)->errorReport($this->batch);
    expect(is_file($errors['path']))->toBeTrue();
});

it('uploads a workbook and shows the inspection page', function () {
    Storage::fake('local');

    $manager = User::factory()->create();
    $manager->assignRole('Data Manager');

    $response = $this->actingAs($manager)->post('/excel/upload', [
        'file' => new UploadedFile(
            base_path('tests/Fixtures/Rainfall Data.xlsx'),
            'Rainfall Data.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        ),
    ]);

    $batch = DataImportBatch::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('excel.preview', ['batch' => $batch->uuid, 'sheet' => 'Arba Minch Raw Data']));
    Storage::disk('local')->assertExists($batch->file_path);

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->actingAs($viewer)
        ->get(route('excel.preview', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Excel/Preview')
            ->has('sheets', 2)
            ->has('detection.candidates')
        );

    $analyst = User::factory()->create();
    $analyst->assignRole('Data Analyst');

    $this->actingAs($analyst)->get('/excel/upload')->assertForbidden();
});
