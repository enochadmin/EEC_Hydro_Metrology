<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use App\Models\DataImportError;
use App\Models\DataQualityIssue;
use App\Models\Parameter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Validates transformed rows without mutating them.
 *
 * Missing values are warnings, not errors: they are genuine absences and must
 * survive into the report. Invalid values, unknown parameters, impossible
 * dates and within-file duplicates are errors and block a clean import.
 */
class ExcelValidationService
{
    /**
     * @return array{summary: array<string, mixed>, issues: list<array<string, mixed>>, path: string}
     */
    public function validate(DataImportBatch $batch): array
    {
        if (! $batch->transformed_path) {
            throw new RuntimeException('Transform the sheet before validating it.');
        }

        $path = Storage::disk('local')->path($batch->transformed_path);

        if (! is_file($path)) {
            throw new RuntimeException('The transformed dataset is missing.');
        }

        $parameters = Parameter::query()->with('defaultUnit:id,code,symbol')->get()->keyBy('code');

        $summary = [
            'rows' => 0,
            'valid_rows' => 0,
            'missing_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'warnings' => 0,
            'errors' => 0,
            'stations' => 0,
            'dates' => 0,
        ];

        $issues = [];
        $issueLimit = (int) config('excel.limits.max_issues_stored', 5000);
        $seen = [];
        $stations = [];
        $dates = [];
        $stationMismatches = 0;
        $stationParameterErrors = 0;
        $unknownParameterErrors = 0;
        $targetStation = $batch->station;
        $stationParameterCodes = $targetStation?->stationParameters()
            ->where('is_active', true)
            ->with('parameter:id,code')
            ->get()
            ->pluck('parameter.code')
            ->filter()
            ->values() ?? collect();
        $targetStationNames = collect([$targetStation?->code, $targetStation?->name, $targetStation?->name_alt])
            ->filter(fn (?string $name): bool => filled($name))
            ->map(fn (string $name): string => mb_strtolower(trim($name)));

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The transformed dataset could not be read.');
        }

        $addIssue = function (array $row, string $code, string $message, string $severity, ?string $column = null, mixed $raw = null) use (&$issues, &$summary, $issueLimit): void {
            $summary[$severity === 'error' ? 'errors' : 'warnings']++;

            if (count($issues) >= $issueLimit) {
                return;
            }

            $issues[] = [
                'row_number' => (int) ($row['row_number'] ?? 0),
                'column' => $column,
                'code' => $code,
                'message' => $message,
                'severity' => $severity,
                'raw_value' => is_scalar($raw) ? (string) $raw : null,
                'date' => $row['date'] ?? null,
                'station' => $row['station_name'] ?? null,
            ];
        };

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);

            if (! is_array($row)) {
                continue;
            }

            $summary['rows']++;

            $station = trim((string) ($row['station_name'] ?? ''));
            $parameterCode = (string) ($row['parameter_code'] ?? '');
            $date = $row['date'] ?? null;

            if ($station === '') {
                $addIssue($row, 'MISSING_STATION', 'The row has no station name.', 'error', 'station_name');
            } else {
                $stations[$station] = $stations[$station] ?? [
                    'name' => $station,
                    'elevation' => $row['elevation'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                    'latitude' => $row['latitude'] ?? null,
                    'rows' => 0,
                ];
                $stations[$station]['rows']++;

                if ($targetStation !== null) {
                    if (! $targetStationNames->contains(mb_strtolower($station))) {
                        $stationMismatches++;
                        $addIssue(
                            $row,
                            'STATION_MISMATCH',
                            'This row identifies station "'.$station.'", but this workbook was uploaded for '.$targetStation->code.' — '.$targetStation->name.'.',
                            'error',
                            'station_name',
                            $station,
                        );
                    }
                }
            }

            if (isset($row['longitude']) && $row['longitude'] !== null) {
                if ((float) $row['longitude'] < -180 || (float) $row['longitude'] > 180) {
                    $addIssue($row, 'INVALID_LONGITUDE', 'Longitude is outside −180…180.', 'error', 'longitude', $row['longitude']);
                }
            }

            if (isset($row['latitude']) && $row['latitude'] !== null) {
                if ((float) $row['latitude'] < -90 || (float) $row['latitude'] > 90) {
                    $addIssue($row, 'INVALID_LATITUDE', 'Latitude is outside −90…90.', 'error', 'latitude', $row['latitude']);
                }
            }

            if ($parameterCode === '' || ! $parameters->has($parameterCode)) {
                $unknownParameterErrors++;
                $addIssue($row, 'UNKNOWN_PARAMETER', 'The element "'.($row['parameter'] ?? '').'" does not match a registered parameter.', 'error', 'parameter', $row['parameter'] ?? null);
            } elseif ($targetStation !== null && ! $stationParameterCodes->contains($parameterCode)) {
                // A station-scoped upload registers the parameter at import
                // time, so this informs the operator instead of blocking it.
                $addIssue(
                    $row,
                    'PARAMETER_WILL_BE_ADDED',
                    'The parameter "'.($row['parameter'] ?? $parameterCode).'" will be added to '.$targetStation->code.' when this workbook is imported.',
                    'warning',
                    'parameter',
                    $row['parameter'] ?? $parameterCode,
                );
            }

            if ($date === null) {
                $addIssue($row, 'INVALID_DATE', $row['message'] ?? 'The date is missing or invalid.', 'error', 'date');
            } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || ! checkdate((int) substr((string) $date, 5, 2), (int) substr((string) $date, 8, 2), (int) substr((string) $date, 0, 4))) {
                $addIssue($row, 'INVALID_DATE', 'The date is not a real calendar date.', 'error', 'date', $date);
            } else {
                $dates[$date] = true;
            }

            $status = $row['status'] ?? 'invalid';

            if ($status === 'missing') {
                $summary['missing_rows']++;
                $addIssue($row, 'MISSING_VALUE', 'No measurement was recorded for this date.', 'warning', 'value');
            } elseif ($status === 'invalid') {
                $summary['invalid_rows']++;
                $addIssue($row, $row['flag'] ?? 'INVALID_VALUE', $row['message'] ?? 'The value is invalid.', 'error', 'value', $row['value'] ?? null);
            } else {
                $summary['valid_rows']++;

                if (($row['parameter_code'] ?? '') === 'rainfall' && is_numeric($row['value'] ?? null) && (float) $row['value'] < 0) {
                    $summary['valid_rows']--;
                    $summary['invalid_rows']++;
                    $addIssue($row, 'NEGATIVE_RAINFALL', 'Rainfall cannot be negative.', 'error', 'value', $row['value']);
                }

                if (! isset($row['value']) || ! is_numeric($row['value'])) {
                    $summary['valid_rows']--;
                    $summary['invalid_rows']++;
                    $addIssue($row, 'INVALID_VALUE', 'A valid row must carry a numeric value.', 'error', 'value', $row['value'] ?? null);
                }
            }

            if ($station !== '' && $date && $parameterCode !== '' && $status !== 'invalid') {
                $key = mb_strtolower($station).'|'.$date.'|'.$parameterCode;

                if (isset($seen[$key])) {
                    $summary['duplicate_rows']++;
                    $addIssue($row, 'DUPLICATE_OBSERVATION', 'This station/date/parameter combination appears more than once in the file.', 'error', null, $date);
                } else {
                    $seen[$key] = true;
                }
            }
        }

        fclose($handle);

        foreach ($stations as $name => $station) {
            if ($station['longitude'] === null || $station['latitude'] === null) {
                $addIssue(
                    ['row_number' => 0, 'station_name' => $name],
                    'MISSING_COORDINATES',
                    'Station "'.$name.'" has no coordinates in the file; match it to a registered station before importing.',
                    'warning',
                    'coordinates',
                );
            }
        }

        $summary['stations'] = count($stations);
        $summary['station_mismatches'] = $stationMismatches;
        $summary['station_parameter_errors'] = $stationParameterErrors;
        $summary['unknown_parameter_errors'] = $unknownParameterErrors;
        $summary['dates'] = count($dates);
        $summary['issues_total'] = count($issues);

        $validationPath = 'excel/'.$batch->uuid.'/validation.json';

        Storage::disk('local')->put($validationPath, json_encode([
            'summary' => $summary,
            'issues' => $issues,
            'stations' => array_values($stations),
        ], JSON_UNESCAPED_UNICODE));

        $this->persistErrors($batch, $issues);
        $this->persistQualityIssues($batch, $summary);

        $batch->forceFill([
            'validation_summary' => $summary,
            'validation_path' => $validationPath,
            'stage' => 'validated',
            'rejected_rows' => $summary['invalid_rows'],
            'total_rows' => $summary['rows'],
        ])->save();

        return ['summary' => $summary, 'issues' => $issues, 'path' => $validationPath];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function persistErrors(DataImportBatch $batch, array $issues): void
    {
        DataImportError::query()->where('data_import_batch_id', $batch->id)->delete();

        foreach (array_chunk($issues, 500) as $chunk) {
            $now = now();

            DB::table('data_import_errors')->insert(array_map(fn (array $issue): array => [
                'data_import_batch_id' => $batch->id,
                'row_number' => $issue['row_number'],
                'column_name' => $issue['column'] !== null ? mb_substr((string) $issue['column'], 0, 100) : null,
                'error_code' => mb_substr($issue['code'], 0, 50),
                'message' => $issue['message'],
                'raw_value' => $issue['raw_value'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function report(DataImportBatch $batch): array
    {
        if (! $batch->validation_path) {
            return ['summary' => [], 'issues' => [], 'stations' => []];
        }

        $path = Storage::disk('local')->path($batch->validation_path);

        if (! is_file($path)) {
            return ['summary' => [], 'issues' => [], 'stations' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['summary' => [], 'issues' => [], 'stations' => []];
    }

    /**
     * Aggregated QC signals for the dashboard; the row-level detail stays in
     * data_import_errors and the downloadable report.
     *
     * @param  array<string, mixed>  $summary
     */
    private function persistQualityIssues(DataImportBatch $batch, array $summary): void
    {
        DataQualityIssue::query()->where('data_import_batch_id', $batch->id)->delete();

        if (($summary['missing_rows'] ?? 0) > 0) {
            DataQualityIssue::create([
                'data_import_batch_id' => $batch->id,
                'issue_type' => 'missing',
                'severity' => 'low',
                'description' => $summary['missing_rows'].' daily values are missing in the transformed dataset.',
                'status' => 'open',
            ]);
        }

        if (($summary['duplicate_rows'] ?? 0) > 0) {
            DataQualityIssue::create([
                'data_import_batch_id' => $batch->id,
                'issue_type' => 'duplicate',
                'severity' => 'medium',
                'description' => $summary['duplicate_rows'].' duplicate station/date/parameter rows were found in the workbook.',
                'status' => 'open',
            ]);
        }

        if (($summary['invalid_rows'] ?? 0) > 0) {
            DataQualityIssue::create([
                'data_import_batch_id' => $batch->id,
                'issue_type' => 'other',
                'severity' => 'high',
                'description' => $summary['invalid_rows'].' rows hold invalid dates or measurements and were excluded from import.',
                'status' => 'open',
            ]);
        }
    }
}
