<?php

use App\Models\Parameter;
use App\Services\Reports\ReportDocumentBuilder;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportQueryService;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
});

it('downloads a basin report in every document format', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 4.0);

    $user = actorWithRole('Data Analyst');

    foreach (['csv', 'xlsx', 'pdf', 'geojson'] as $format) {
        $response = $this->actingAs($user)->get("/reports/basins/{$basin->id}/export/{$format}");

        $response->assertOk();
        expect($response->headers->get('content-disposition'))->toContain('.'.$format);
    }
});

it('writes a csv with a byte order mark and the report sections', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 4.0);

    $document = app(ReportDocumentBuilder::class)->basin(app(ReportQueryService::class)->basin($basin));
    $file = app(ReportExportService::class)->csv($document);

    $bytes = file_get_contents($file['path']);
    unlink($file['path']);

    expect(str_starts_with($bytes, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($bytes)->toContain('Basin report')
        ->and($bytes)->toContain('Test basin')
        ->and($bytes)->toContain('Parameters and data coverage');
});

it('writes a readable pdf document', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 4.0);

    $document = app(ReportDocumentBuilder::class)->basin(app(ReportQueryService::class)->basin($basin));
    $file = app(ReportExportService::class)->pdf($document);

    $bytes = file_get_contents($file['path']);
    unlink($file['path']);

    expect(str_starts_with($bytes, '%PDF-1.4'))->toBeTrue()
        ->and($bytes)->toContain('/Type /Page')
        ->and($bytes)->toContain('Basin report')
        ->and(str_ends_with($bytes, '%%EOF'))->toBeTrue();
});

it('writes an xlsx workbook', function () {
    [$basin, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 4.0);

    $document = app(ReportDocumentBuilder::class)->basin(app(ReportQueryService::class)->basin($basin));
    $file = app(ReportExportService::class)->xlsx($document);

    $bytes = file_get_contents($file['path']);
    unlink($file['path']);

    expect(str_starts_with($bytes, "PK"))->toBeTrue()
        ->and(strlen($bytes))->toBeGreaterThan(2000);
});

it('exports geographic records as geoJSON in EPSG:4326', function () {
    [$basin] = reportFixture();

    $features = app(ReportQueryService::class)->basinFeatures($basin);

    expect($features)->toHaveCount(1)
        ->and($features[0]['geometry']['type'])->toBe('Point')
        ->and($features[0]['geometry']['coordinates'])->toBe([40.15, 9.05])
        ->and($features[0]['properties']['layer'])->toBe('monitoring_station');

    $file = app(ReportExportService::class)->geoJson($features, 'basin-TST-geography');
    $payload = json_decode(file_get_contents($file['path']), true);
    unlink($file['path']);

    expect($payload['type'])->toBe('FeatureCollection')
        ->and($payload['crs']['properties']['name'])->toContain('CRS84')
        ->and($payload['features'])->toHaveCount(1);
});

it('marks totals as not applicable for a state parameter', function () {
    [$basin, $station] = reportFixture();
    $temperature = Parameter::where('code', 'temperature')->firstOrFail();
    reportObserve($station->id, $temperature->id, '2020-07-01 09:00:00', 25.0);

    $result = [
        'series' => app(\App\Services\Reports\HydrologicalAnalysisService::class)->series([
            'parameter_id' => $temperature->id,
            'basin_id' => $basin->id,
        ], 'monthly'),
        'coverage' => app(\App\Services\Reports\HydrologicalAnalysisService::class)->coverage([
            'parameter_id' => $temperature->id,
            'basin_id' => $basin->id,
        ]),
    ];

    $document = app(ReportDocumentBuilder::class)->analysis(
        $result,
        ['parameter_id' => $temperature->id, 'from' => null, 'to' => null],
        'Test basin'
    );

    $summary = $document['sections'][0]['rows'];
    $total = collect($summary)->firstWhere(0, 'Total');

    expect($total[1])->toBe('Not applicable');
});
