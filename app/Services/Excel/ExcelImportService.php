<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Commits validated observations.
 *
 * Parameters are never created from a file. Stations are created only when the
 * user explicitly chose "create" in the matching step, and always under a
 * chosen basin. The unique observation key protects against re-imports.
 */
class ExcelImportService
{
    public function __construct(private readonly ExcelValidationService $validation) {}

    private function coordinate(array $entry, mixed $fallback, string $key): ?float
    {
        $value = $entry[$key] ?? ($fallback[$key] ?? null);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $stationMap
     * @return array<string, int>
     */
    public function import(DataImportBatch $batch, array $stationMap, User $actor): array
    {
        if (! $batch->transformed_path || ! $batch->validation_summary) {
            throw new RuntimeException('Validate the transformed dataset before importing it.');
        }

        $path = Storage::disk('local')->path($batch->transformed_path);

        if (! is_file($path)) {
            throw new RuntimeException('The transformed dataset is missing.');
        }

        $parameters = Parameter::query()->with('defaultUnit:id,code,symbol')->get()->keyBy('code');
        $fileStations = collect($this->validation->report($batch)['stations'] ?? [])->keyBy('name');

        $resolved = [];
        $stationsToCreate = [];

        foreach ($stationMap as $name => $entry) {
            $mode = $entry['mode'] ?? 'existing';

            if ($mode === 'create') {
                $stationsToCreate[$name] = $entry;
            } elseif (! empty($entry['station_id'])) {
                $resolved[$name] = (int) $entry['station_id'];
            }
        }

        foreach ($stationsToCreate as $name => $entry) {
            $code = trim((string) ($entry['code'] ?? ''));

            if ($code === '') {
                throw ValidationException::withMessages(['station_map' => 'A station code is required when creating "'.$name.'".']);
            }

            if (MonitoringStation::withTrashed()->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'station_map' => 'Station code '.$code.' already exists. Select the existing station instead of creating a new one.',
                ]);
            }

            if (empty($entry['basin_id'])) {
                throw ValidationException::withMessages(['station_map' => 'Choose a basin for the new station "'.$name.'".']);
            }

            if (MonitoringStation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages([
                    'station_map' => 'A station named "'.$name.'" is already registered. Select it instead of creating a second record.',
                ]);
            }
        }

        $counts = [
            'total_rows' => 0,
            'valid_rows' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'skipped' => 0,
        ];

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The transformed dataset could not be read.');
        }

        $now = now();
        $chunk = [];

        DB::beginTransaction();

        try {
            foreach ($stationsToCreate as $name => $entry) {
                $lat = $this->coordinate($entry, $fileStations->get($name), 'latitude');
                $lng = $this->coordinate($entry, $fileStations->get($name), 'longitude');
                $basinId = (int) $entry['basin_id'];
                $subBasinId = ! empty($entry['sub_basin_id']) ? (int) $entry['sub_basin_id'] : null;

                if ($subBasinId === null && $lat !== null && $lng !== null) {
                    $matched = DB::selectOne(
                        'SELECT id FROM sub_basins WHERE basin_id = ? AND boundary IS NOT NULL AND ST_Contains(boundary, ST_GeomFromText(?, 4326)) LIMIT 1',
                        [$basinId, "POINT({$lng} {$lat})"]
                    );
                    if ($matched) {
                        $subBasinId = (int) $matched->id;
                    }
                }

                $station = MonitoringStation::create([
                    'code' => strtoupper(trim((string) $entry['code'])),
                    'name' => trim((string) ($entry['name'] ?? $name)),
                    'basin_id' => $basinId,
                    'sub_basin_id' => $subBasinId,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'elevation_m' => $this->coordinate($entry, $fileStations->get($name), 'elevation'),
                    'station_type' => config('excel.station_defaults.type', 'rainfall'),
                    'data_status' => config('excel.station_defaults.data_status', 'provisional'),
                    'is_active' => true,
                    'notes' => 'Created from Excel import '.$batch->file_name.'.',
                ]);

                $resolved[$name] = $station->id;
            }

            $flush = function () use (&$chunk, &$counts): void {
                if ($chunk === []) {
                    return;
                }

                $inserted = DB::table('hydrological_observations')->insertOrIgnore($chunk);
                $counts['imported'] += $inserted;
                $counts['duplicates'] += count($chunk) - $inserted;
                $chunk = [];
            };

            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);

                if (! is_array($row)) {
                    continue;
                }

                $counts['total_rows']++;

                if (($row['status'] ?? '') !== ExcelTransformationService::STATUS_VALID) {
                    $counts['skipped']++;

                    continue;
                }

                $counts['valid_rows']++;

                $stationName = trim((string) ($row['station_name'] ?? ''));
                $stationId = $resolved[$stationName] ?? null;

                if ($stationId === null) {
                    $stationId = MonitoringStation::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($stationName)])
                        ->value('id');
                }

                if ($stationId === null) {
                    throw ValidationException::withMessages([
                        'station_map' => 'Station "'.$stationName.'" has not been matched to a registered station.',
                    ]);
                }

                $parameterCode = (string) ($row['parameter_code'] ?? '');
                $parameter = $parameters->get($parameterCode);

                if ($parameter === null) {
                    throw ValidationException::withMessages([
                        'parameter_map' => 'Element "'.($row['parameter'] ?? '').'" does not match a registered parameter. Map it before importing.',
                    ]);
                }

                $measuredAt = CarbonImmutable::parse($row['date'].' '.($row['time'] ?? '09:00'));

                $chunk[] = [
                    'monitoring_station_id' => $stationId,
                    'parameter_id' => $parameter->id,
                    'measurement_unit_id' => $parameter->default_unit_id,
                    'measured_at' => $measuredAt->format('Y-m-d H:i:s'),
                    'value' => (float) $row['value'],
                    'series_no' => 1,
                    'record_type' => 'observed',
                    'quality_status' => 'approved',
                    'data_source_id' => $batch->data_source_id,
                    'data_import_batch_id' => $batch->id,
                    'import_row_number' => $row['row_number'] ?? null,
                    'entered_by' => $actor->id,
                    'notes' => 'Imported from '.$batch->sheet_name.' ('.$batch->structure_type.').',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($chunk) >= (int) config('excel.limits.import_chunk', 500)) {
                    $flush();
                }
            }

            fclose($handle);
            $flush();

            $batch->forceFill([
                'status' => $counts['skipped'] > 0 ? 'completed_with_errors' : 'completed',
                'stage' => 'imported',
                'total_rows' => $counts['total_rows'],
                'imported_rows' => $counts['imported'],
                'duplicate_rows' => $counts['duplicates'],
                'rejected_rows' => $counts['skipped'],
                'approved_at' => $now,
                'approved_by' => $actor->id,
                'imported_at' => $now,
                'completed_at' => $now,
                'station_map' => $stationMap,
            ])->save();

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            if (is_resource($handle)) {
                fclose($handle);
            }

            throw $exception;
        }

        return $counts;
    }
}
