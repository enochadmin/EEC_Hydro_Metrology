<?php

use App\Models\Parameter;
use App\Services\Reports\HydrologicalAnalysisService;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;

beforeEach(function () {
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
});

function analysisFor(int $parameterId, int $stationId, string $granularity = 'monthly'): array
{
    return app(HydrologicalAnalysisService::class)->series([
        'parameter_id' => $parameterId,
        'station_id' => $stationId,
    ], $granularity);
}

it('sums an accumulating parameter and averages a state parameter', function () {
    [, $station] = reportFixture();

    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();
    $temperature = Parameter::where('code', 'temperature')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2020-01-05 09:00:00', 12.4);
    reportObserve($station->id, $rainfall->id, '2020-01-06 09:00:00', 7.6);

    reportObserve($station->id, $temperature->id, '2020-01-05 09:00:00', 21.0);
    reportObserve($station->id, $temperature->id, '2020-01-06 09:00:00', 27.0);

    $rain = analysisFor($rainfall->id, $station->id);
    $temp = analysisFor($temperature->id, $station->id);

    expect($rain['metric'])->toBe('total')
        ->and($rain['points'])->toHaveCount(1)
        ->and($rain['points'][0]['value'])->toBe(20.0)
        ->and($rain['points'][0]['mean'])->toBe(10.0)
        ->and($rain['points'][0]['min'])->toBe(7.6)
        ->and($rain['points'][0]['max'])->toBe(12.4);

    expect($temp['metric'])->toBe('mean')
        ->and($temp['points'])->toHaveCount(1)
        ->and($temp['points'][0]['value'])->toBe(24.0)
        ->and($temp['points'][0]['min'])->toBe(21.0)
        ->and($temp['points'][0]['max'])->toBe(27.0);
});

it('groups by the requested granularity', function () {
    [, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2019-12-31 09:00:00', 4.0);
    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 6.0);
    reportObserve($station->id, $rainfall->id, '2020-02-10 09:00:00', 9.0);

    $annual = analysisFor($rainfall->id, $station->id, 'annual');
    $monthly = analysisFor($rainfall->id, $station->id, 'monthly');
    $daily = analysisFor($rainfall->id, $station->id, 'daily');

    expect($annual['points'])->toHaveCount(2)
        ->and($annual['points'][0]['label'])->toBe('2019')
        ->and($annual['points'][1]['value'])->toBe(15.0);

    expect($monthly['points'])->toHaveCount(3);

    expect($daily['points'])->toHaveCount(3)
        ->and($daily['points'][0]['label'])->toContain('2019');
});

it('excludes provisional readings unless all records are requested', function () {
    [, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2020-05-01 09:00:00', 10.0, 'approved');
    reportObserve($station->id, $rainfall->id, '2020-05-02 09:00:00', 5.0, 'pending');

    $approved = app(HydrologicalAnalysisService::class)->series([
        'parameter_id' => $rainfall->id,
        'station_id' => $station->id,
        'quality' => 'approved',
    ], 'monthly');

    $all = app(HydrologicalAnalysisService::class)->series([
        'parameter_id' => $rainfall->id,
        'station_id' => $station->id,
        'quality' => 'all',
    ], 'monthly');

    expect($approved['points'][0]['value'])->toBe(10.0)
        ->and($all['points'][0]['value'])->toBe(15.0);
});

it('reports the gap between the first and last record', function () {
    [, $station] = reportFixture();
    $rainfall = Parameter::where('code', 'rainfall')->firstOrFail();

    reportObserve($station->id, $rainfall->id, '2020-01-01 09:00:00', 1.0);
    reportObserve($station->id, $rainfall->id, '2020-01-11 09:00:00', 2.0);

    $coverage = app(HydrologicalAnalysisService::class)->coverage([
        'parameter_id' => $rainfall->id,
        'station_id' => $station->id,
    ]);

    expect($coverage['records'])->toBe(2)
        ->and($coverage['span_days'])->toBe(11)
        ->and($coverage['observed_days'])->toBe(2)
        ->and($coverage['missing_days'])->toBe(9)
        ->and($coverage['approved_percent'])->toBe(100.0);
});
