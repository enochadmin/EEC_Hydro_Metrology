<?php

namespace App\Services\Stations;

use App\Models\DataImportBatch;
use App\Models\MonitoringStation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Compares two uploads of the same station, parameter by parameter.
 *
 * A station is often delivered twice: as a row-per-date list and as a
 * month-per-column matrix. Both are transformed to the same canonical
 * `date → value` series by the Excel pipeline, so the comparison happens on
 * calendar dates rather than on sheet geometry. Differences are reported, never
 * reconciled: the operator decides which upload is authoritative.
 */
class StationDataComparisonService
{
    /**
     * Uploads for one station that carry a transformed dataset.
     *
     * @return Collection<int, DataImportBatch>
     */
    public function batchesFor(MonitoringStation $station): Collection
    {
        return DataImportBatch::query()
            ->where('monitoring_station_id', $station->id)
            ->whereNotNull('transformed_path')
            ->latest()
            ->limit(40)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function compare(DataImportBatch $left, DataImportBatch $right): array
    {
        if ((int) $left->monitoring_station_id !== (int) $right->monitoring_station_id) {
            throw new RuntimeException('Both uploads must belong to the same station.');
        }

        $leftSeries = $this->series($left);
        $rightSeries = $this->series($right);

        $dates = array_values(array_unique([...array_keys($leftSeries), ...array_keys($rightSeries)]));
        sort($dates);

        $counts = [
            'dates' => 0,
            'matching' => 0,
            'different' => 0,
            'only_left' => 0,
            'only_right' => 0,
            'missing_values' => 0,
        ];

        $differences = [];
        $monthly = [];
        $absoluteTotal = 0.0;
        $differenceTotal = 0.0;
        $maxDifference = 0.0;

        foreach ($dates as $date) {
            $leftValue = $leftSeries[$date]['value'] ?? null;
            $rightValue = $rightSeries[$date]['value'] ?? null;

            if ($leftValue === null && $rightValue === null) {
                $counts['missing_values']++;
                $monthly[substr($date, 0, 7)] ??= ['month' => substr($date, 0, 7), 'left' => 0.0, 'right' => 0.0, 'differences' => 0];

                continue;
            }

            $counts['dates']++;
            $month = substr($date, 0, 7);
            $monthly[$month] ??= ['month' => $month, 'left' => 0.0, 'right' => 0.0, 'differences' => 0];

            if ($leftValue !== null) {
                $monthly[$month]['left'] += $leftValue;
            }

            if ($rightValue !== null) {
                $monthly[$month]['right'] += $rightValue;
            }

            if ($leftValue === null) {
                $counts['only_right']++;
                $differences[] = ['date' => $date, 'left' => null, 'right' => $rightValue, 'difference' => null, 'kind' => 'only_right'];

                continue;
            }

            if ($rightValue === null) {
                $counts['only_left']++;
                $differences[] = ['date' => $date, 'left' => $leftValue, 'right' => null, 'difference' => null, 'kind' => 'only_left'];

                continue;
            }

            $difference = round($leftValue - $rightValue, 4);
            $absoluteTotal += abs($difference);
            $differenceTotal += $difference;
            $maxDifference = max($maxDifference, abs($difference));

            if (abs($difference) < 0.00005) {
                $counts['matching']++;

                continue;
            }

            $counts['different']++;
            $monthly[$month]['differences']++;
            $differences[] = ['date' => $date, 'left' => $leftValue, 'right' => $rightValue, 'difference' => $difference, 'kind' => 'different'];
        }

        $counts['compared_values'] = $counts['matching'] + $counts['different'];

        return [
            'left' => $this->batchPayload($left, $leftSeries),
            'right' => $this->batchPayload($right, $rightSeries),
            'parameter' => [
                'left' => $left->structure_type,
                'right' => $right->structure_type,
                'code' => $this->commonParameter($left, $right),
            ],
            'summary' => [
                ...$counts,
                'mean_absolute_difference' => $counts['compared_values'] > 0 ? round($absoluteTotal / $counts['compared_values'], 4) : 0.0,
                'mean_difference' => $counts['compared_values'] > 0 ? round($differenceTotal / $counts['compared_values'], 4) : 0.0,
                'max_difference' => round($maxDifference, 4),
                'agreement_pct' => ($counts['matching'] + $counts['different']) > 0
                    ? round(($counts['matching'] / ($counts['matching'] + $counts['different'])) * 100, 1)
                    : 0.0,
            ],
            'differences' => array_slice($differences, 0, 300),
            'differences_total' => count($differences),
            'monthly' => array_values(array_map(
                fn (array $row): array => [
                    'month' => $row['month'],
                    'left' => round($row['left'], 2),
                    'right' => round($row['right'], 2),
                    'difference' => round($row['left'] - $row['right'], 2),
                    'differences' => $row['differences'],
                ],
                $monthly,
            )),
        ];
    }

    /**
     * Canonical `date → value` map read from the transformed NDJSON.
     *
     * @return array<string, array{value: float|null, status: string}>
     */
    private function series(DataImportBatch $batch): array
    {
        if (! $batch->transformed_path) {
            throw new RuntimeException('The upload has not been transformed yet.');
        }

        $path = Storage::disk('local')->path($batch->transformed_path);

        if (! is_file($path)) {
            throw new RuntimeException('The transformed dataset for '.$batch->file_name.' is missing.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The transformed dataset could not be read.');
        }

        $series = [];

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);

            if (! is_array($row) || empty($row['date'])) {
                continue;
            }

            $date = (string) $row['date'];
            $status = (string) ($row['status'] ?? 'invalid');
            $value = $status === 'valid' && is_numeric($row['value'] ?? null) ? (float) $row['value'] : null;

            if (isset($series[$date]) && $series[$date]['value'] !== null) {
                continue;
            }

            $series[$date] = ['value' => $value, 'status' => $status];
        }

        fclose($handle);

        ksort($series);

        return $series;
    }

    /**
     * @param  array<string, array{value: float|null, status: string}>  $series
     * @return array<string, mixed>
     */
    private function batchPayload(DataImportBatch $batch, array $series): array
    {
        $dates = array_keys($series);

        return [
            'uuid' => $batch->uuid,
            'file_name' => $batch->file_name,
            'sheet_name' => $batch->sheet_name,
            'structure_type' => $batch->structure_type,
            'stage' => $batch->stage,
            'status' => $batch->status,
            'dates' => count($dates),
            'from' => $dates === [] ? null : $dates[0],
            'to' => $dates === [] ? null : $dates[array_key_last($dates)],
            'imported_at' => $batch->imported_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    private function commonParameter(DataImportBatch $left, DataImportBatch $right): ?string
    {
        $leftParameter = $this->parameterOf($left);
        $rightParameter = $this->parameterOf($right);

        return $leftParameter !== null && $leftParameter === $rightParameter ? $leftParameter : null;
    }

    private function parameterOf(DataImportBatch $batch): ?string
    {
        return $batch->mapping['parameter_code']
            ?? $batch->station_map['parameter_code']
            ?? null;
    }
}
