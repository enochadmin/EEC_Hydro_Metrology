<?php

namespace App\Services\Stations;

use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\StationImportBatch;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\Geo\CoordinateTransformer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Imports a station registry workbook (name, coordinates, network class).
 *
 * Nothing is written to `monitoring_stations` during the preview: rows are
 * converted, classified and checked for duplicates, then staged as NDJSON so
 * the operator can review the exact records that will be created. Commit
 * re-runs the preview with the submitted options and inserts inside one
 * transaction, so a rejected row can never leave a half-imported network.
 */
class StationRegistryImporter
{
    /** Header aliases, in priority order. */
    private const ALIASES = [
        'name' => ['station name', 'station', 'name', 'site name', 'site', 'stname'],
        'code' => ['station code', 'code', 'site code'],
        'sequence' => ['no.', 'no', 's/n', 'sn', '#', 'number', 'seq', 'id'],
        'latitude' => ['latitude', 'lat', 'y coord', 'y-coordinate'],
        'longitude' => ['longitude', 'lon', 'long', 'lng', 'x coord', 'x-coordinate'],
        'easting' => ['utm easting', 'easting', 'x'],
        'northing' => ['utm northing', 'northing', 'y'],
        'class' => ['station class', 'class 1', 'class1', 'class', 'category', 'network', 'type'],
        'elevation' => ['elevation (m)', 'elevation_m', 'elevation', 'elev m', 'altitude', 'elev'],
        'operator' => ['operator', 'agency', 'organization', 'organisation', 'institution'],
    ];

    public function __construct(
        private readonly CoordinateTransformer $coordinates,
        private readonly ActivityLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'code_prefix' => config('stations.import.code_prefix'),
            'utm_zone' => (int) config('stations.import.utm_zone'),
            'coordinates' => 'auto',
            'basin_id' => null,
            'sub_basin_id' => null,
            'data_source_id' => null,
            'data_status' => config('stations.import.default_data_status'),
            'parameters' => config('stations.import.default_parameters'),
            'skip_duplicates' => true,
            'operator' => null,
        ];
    }

    /**
     * Parse, convert and validate the workbook; stage the rows.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function preview(StationImportBatch $batch, array $options): array
    {
        $options = $this->normaliseOptions($options);
        [$headers, $rows, $headerRow] = $this->readSheet($batch);
        $mapping = $this->detectColumns($headers, $headerRow);

        if ($mapping['name'] === null) {
            throw new RuntimeException('No name column was found. Include a station name header (for example "NAME" or "Station").');
        }

        $parameters = Parameter::query()
            ->where('is_active', true)
            ->whereIn('code', $options['parameters'])
            ->get(['id', 'code', 'default_unit_id'])
            ->keyBy('code');

        $existing = $this->existingStations($rows, $mapping);
        $codes = $this->existingCodes((string) $options['code_prefix']);
        $generated = [];

        $staged = [];
        $issues = [];
        $classCounts = [];
        $statusCounts = ['ready' => 0, 'duplicate' => 0, 'error' => 0];
        $severityCounts = ['error' => 0, 'warning' => 0];
        $outsideEthiopia = 0;
        $projected = null;
        $latitudes = [];
        $longitudes = [];
        $seen = [];

        foreach ($rows as $index => $cells) {
            $rowNumber = $mapping['header_row'] + $index + 1;
            $name = $this->text($cells[$mapping['name']] ?? null);

            if ($name === null) {
                continue;
            }

            $name = $this->normaliseName($name);
            $rowIssues = [];

            $classRaw = $mapping['class'] !== null ? $this->text($cells[$mapping['class']] ?? null) : null;
            $class = $this->normaliseClass($classRaw);
            $classCounts[$class ?? 'other'] = ($classCounts[$class ?? 'other'] ?? 0) + 1;

            $sequence = null;

            if ($mapping['sequence'] !== null) {
                $sequence = $this->integer($cells[$mapping['sequence']] ?? null);
            }

            $coordinates = $this->convertCoordinates($cells, $mapping, $options, $rowIssues, $projected);

            if ($coordinates === null) {
                $rowIssues[] = [
                    'code' => 'INVALID_COORDINATES',
                    'severity' => 'error',
                    'message' => 'The row has no usable coordinate pair.',
                    'column' => 'latitude',
                ];
            } elseif (! $this->coordinates->insideEthiopia($coordinates['latitude'], $coordinates['longitude'])) {
                $outsideEthiopia++;
                $rowIssues[] = [
                    'code' => 'OUTSIDE_ETHIOPIA',
                    'severity' => 'warning',
                    'message' => 'Converted position falls outside the Ethiopian extent.',
                    'column' => 'latitude',
                ];
            }

            $duplicate = null;

            if ($coordinates !== null) {
                $duplicate = $this->findDuplicate($existing, $name, $coordinates);
            }

            if ($duplicate !== null) {
                $rowIssues[] = [
                    'code' => 'DUPLICATE_STATION',
                    'severity' => 'duplicate',
                    'message' => 'Matches registered station '.$duplicate['code'].' — '.$duplicate['name'].' within '.$duplicate['distance_m'].' m.',
                    'column' => 'name',
                ];
            }

            $key = $this->duplicateKey($name, $coordinates);

            if ($coordinates !== null && isset($seen[$key])) {
                $rowIssues[] = [
                    'code' => 'DUPLICATE_IN_FILE',
                    'severity' => 'error',
                    'message' => 'Another row in this file already carries this name and position (row '.$seen[$key].').',
                    'column' => 'name',
                ];
            } elseif ($coordinates !== null) {
                $seen[$key] = $rowNumber;
            }

            $hasError = collect($rowIssues)->contains(fn (array $issue): bool => $issue['severity'] === 'error');
            $status = $hasError ? 'error' : ($duplicate !== null ? 'duplicate' : 'ready');

            if ($class === null) {
                $rowIssues[] = [
                    'code' => 'MISSING_CLASS',
                    'severity' => 'warning',
                    'message' => 'No network class in the file; stored as unclassified.',
                    'column' => 'class',
                ];
            }

            $code = $this->nextCode($mapping['code'] !== null ? $this->text($cells[$mapping['code']] ?? null) : null, $sequence, $index + 1, (string) $options['code_prefix'], $codes, $generated, $name);

            if ($coordinates !== null) {
                $latitudes[] = $coordinates['latitude'];
                $longitudes[] = $coordinates['longitude'];
            }

            $statusCounts[$status]++;

            foreach ($rowIssues as $issue) {
                if ($issue['severity'] === 'error') {
                    $severityCounts['error']++;
                } elseif ($issue['severity'] === 'warning') {
                    $severityCounts['warning']++;
                }

                $issues[] = [
                    'row_number' => $rowNumber,
                    'station' => $name,
                    ...$issue,
                ];
            }

            $staged[] = [
                'row_number' => $rowNumber,
                'name' => $name,
                'code' => $code,
                'sequence' => $sequence,
                'station_class' => $class,
                'class_raw' => $classRaw,
                'station_type' => $this->stationTypeFor($class),
                'latitude' => $coordinates['latitude'] ?? null,
                'longitude' => $coordinates['longitude'] ?? null,
                'raw_latitude' => $this->numeric($cells[$mapping['latitude']] ?? ($cells[$mapping['easting']] ?? null)),
                'raw_longitude' => $this->numeric($cells[$mapping['longitude']] ?? ($cells[$mapping['northing']] ?? null)),
                'elevation_m' => $mapping['elevation'] !== null ? $this->numeric($cells[$mapping['elevation']] ?? null) : null,
                'operator' => $mapping['operator'] !== null ? $this->text($cells[$mapping['operator']] ?? null) : $options['operator'],
                'status' => $status,
                'duplicate_of' => $duplicate,
                'issues' => $rowIssues,
            ];
        }

        $stagedPath = 'station-imports/'.$batch->uuid.'/staged.jsonl';
        $handle = fopen(Storage::disk('local')->path($stagedPath), 'wb');

        if ($handle === false) {
            throw new RuntimeException('The staged file could not be written.');
        }

        foreach ($staged as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);
        }

        fclose($handle);

        $summary = [
            'rows' => count($staged),
            'ready' => $statusCounts['ready'],
            'duplicates' => $statusCounts['duplicate'],
            'errors' => $statusCounts['error'],
            'warnings' => $severityCounts['warning'],
            'outside_ethiopia' => $outsideEthiopia,
            'classes' => $classCounts,
            'coordinates' => $projected ? 'projected' : 'geographic',
            'utm_zone' => $options['utm_zone'],
            'latitude_range' => $latitudes === [] ? null : [min($latitudes), max($latitudes)],
            'longitude_range' => $longitudes === [] ? null : [min($longitudes), max($longitudes)],
            'parameters' => $parameters->keys()->all(),
            'columns' => $mapping['detected'],
            'sheets' => $this->sheetNames($batch),
        ];

        $batch->forceFill([
            'status' => 'previewed',
            'stage' => 'previewed',
            'options' => $options,
            'mapping' => $mapping,
            'summary' => $summary,
            'staged_path' => $stagedPath,
            'total_rows' => count($staged),
            'importable_rows' => $statusCounts['ready'],
            'duplicate_rows' => $statusCounts['duplicate'],
            'error_rows' => $statusCounts['error'],
        ])->save();

        $batch->errors()->delete();

        foreach (array_slice($issues, 0, 2000) as $issue) {
            $batch->errors()->create([
                'row_number' => $issue['row_number'],
                'column_name' => $issue['column'] ?? null,
                'code' => $issue['code'],
                'severity' => $issue['severity'] === 'duplicate' ? 'warning' : $issue['severity'],
                'message' => mb_substr($issue['message'], 0, 500),
                'raw_value' => null,
            ]);
        }

        return $summary;
    }

    /**
     * Staged rows for the preview table.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function stagedRows(StationImportBatch $batch, int $limit = 100, int $offset = 0): array
    {
        if (! $batch->staged_path) {
            return ['rows' => [], 'total' => 0];
        }

        $path = Storage::disk('local')->path($batch->staged_path);

        if (! is_file($path)) {
            return ['rows' => [], 'total' => 0];
        }

        $rows = [];
        $position = 0;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['rows' => [], 'total' => 0];
        }

        while (($line = fgets($handle)) !== false) {
            if ($position++ < $offset) {
                continue;
            }

            $decoded = json_decode(trim($line), true);

            if (is_array($decoded)) {
                $rows[] = $decoded;
            }

            if (count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return ['rows' => $rows, 'total' => (int) $batch->total_rows];
    }

    /**
     * Insert the staged rows that are still importable.
     *
     * @param  array<string, mixed>  $options
     * @return array{imported: int, duplicates: int, errors: int, stations: list<string>}
     */
    public function commit(StationImportBatch $batch, array $options, User $actor): array
    {
        $options = $this->normaliseOptions($options);
        $summary = $this->preview($batch, $options);
        $staged = $this->stagedRows($batch, max(1, (int) $batch->total_rows))['rows'];

        $parameters = Parameter::query()
            ->where('is_active', true)
            ->whereIn('code', $options['parameters'])
            ->get(['id', 'code', 'default_unit_id']);

        $ready = array_values(array_filter($staged, fn (array $row): bool => ($row['status'] ?? '') === 'ready'));

        if ($ready === [] && $summary['ready'] === 0) {
            return ['imported' => 0, 'duplicates' => (int) $summary['duplicates'], 'errors' => (int) $summary['errors'], 'stations' => []];
        }

        $now = now();
        $records = [];

        foreach ($ready as $row) {
            $records[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'basin_id' => $options['basin_id'],
                'sub_basin_id' => $options['sub_basin_id'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'elevation_m' => $row['elevation_m'],
                'station_type' => $row['station_type'],
                'station_class' => $row['station_class'],
                'operator' => $row['operator'] ?? $options['operator'],
                'data_source_id' => $options['data_source_id'],
                'data_status' => $options['data_status'],
                'is_active' => true,
                'notes' => 'Imported from station registry '.$batch->file_name.' (row '.$row['row_number'].').',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::beginTransaction();

        try {
            foreach (array_chunk($records, 200) as $chunk) {
                DB::table('monitoring_stations')->insert($chunk);
            }

            $codes = array_column($records, 'code');
            $stationIds = MonitoringStation::withTrashed()
                ->whereIn('code', $codes)
                ->pluck('id', 'code');

            $pivot = [];

            if ($parameters->isNotEmpty()) {
                foreach ($stationIds as $code => $id) {
                    foreach ($parameters as $parameter) {
                        $pivot[] = [
                            'monitoring_station_id' => $id,
                            'parameter_id' => $parameter->id,
                            'measurement_unit_id' => $parameter->default_unit_id,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk($pivot, 400) as $chunk) {
                DB::table('station_parameters')->insertOrIgnore($chunk);
            }

            $batch->forceFill([
                'status' => 'imported',
                'stage' => 'imported',
                'imported_rows' => count($records),
                'imported_by' => $actor->id,
                'imported_at' => $now,
            ])->save();

            $this->audit->log(
                'stations.imported',
                $batch,
                sprintf('Imported %d stations from %s.', count($records), $batch->file_name),
                [
                    'stations' => count($records),
                    'duplicates' => $summary['duplicates'],
                    'errors' => $summary['errors'],
                    'coordinates' => $summary['coordinates'],
                    'utm_zone' => $summary['utm_zone'],
                ],
                $actor,
            );

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return [
            'imported' => count($records),
            'duplicates' => (int) $summary['duplicates'],
            'errors' => (int) $summary['errors'],
            'stations' => array_slice($codes, 0, 5),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function normaliseOptions(array $options): array
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($options['code_prefix'] ?? config('stations.import.code_prefix'))) ?? '');

        $status = (string) ($options['data_status'] ?? config('stations.import.default_data_status'));

        if (! in_array($status, ['pending', 'provisional', 'verified'], true)) {
            $status = 'provisional';
        }


        $zone = (int) ($options['utm_zone'] ?? config('stations.import.utm_zone'));

        if ($zone < 1 || $zone > 60) {
            $zone = (int) config('stations.import.utm_zone');
        }

        $coordinateMode = (string) ($options['coordinates'] ?? 'auto');

        if (! in_array($coordinateMode, ['auto', 'projected', 'degrees'], true)) {
            $coordinateMode = 'auto';
        }

        return [
            'code_prefix' => $prefix !== '' ? $prefix : 'MET',
            'utm_zone' => $zone,
            'coordinates' => $coordinateMode,
            'basin_id' => ! empty($options['basin_id']) ? (int) $options['basin_id'] : null,
            'sub_basin_id' => ! empty($options['sub_basin_id']) ? (int) $options['sub_basin_id'] : null,
            'data_source_id' => ! empty($options['data_source_id']) ? (int) $options['data_source_id'] : null,
            'data_status' => $status,
            'parameters' => array_values(array_filter(array_map('strval', (array) ($options['parameters'] ?? [])))),
            'skip_duplicates' => (bool) ($options['skip_duplicates'] ?? true),
            'operator' => isset($options['operator']) && trim((string) $options['operator']) !== '' ? trim((string) $options['operator']) : null,
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function readSheet(StationImportBatch $batch): array
    {
        $path = Storage::disk('local')->path((string) $batch->file_path);

        if (! is_file($path)) {
            throw new RuntimeException('The uploaded workbook is missing from disk.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        $maxRow = min($sheet->getHighestDataRow(), (int) config('stations.import.max_rows', 20000) + 20);
        $maxColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 30);

        $matrix = $sheet->rangeToArray(
            'A1:'.Coordinate::stringFromColumnIndex($maxColumn).$maxRow,
            null,
            false,
            false,
            false,
        );

        $headerIndex = $this->findHeaderRow($matrix);
        $mapping = $this->detectColumns($matrix[$headerIndex] ?? [], $headerIndex + 1);

        if ($mapping['name'] === null) {
            $headerIndex = 0;
        }

        return [$matrix[$headerIndex] ?? [], array_slice($matrix, $headerIndex + 1), $headerIndex + 1];
    }

    /**
     * @return list<string>
     */
    private function sheetNames(StationImportBatch $batch): array
    {
        $path = Storage::disk('local')->path((string) $batch->file_path);

        try {
            return array_map(
                fn (array $sheet): string => (string) $sheet['worksheetName'],
                IOFactory::createReaderForFile($path)->listWorksheetInfo($path),
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function findHeaderRow(array $matrix): int
    {
        $limit = min(count($matrix), 12);

        for ($index = 0; $index < $limit; $index++) {
            $cells = array_map(fn ($cell): string => strtolower(trim((string) $cell)), $matrix[$index] ?? []);

            foreach (self::ALIASES['name'] as $alias) {
                if (in_array($alias, $cells, true)) {
                    return $index;
                }
            }
        }

        return 0;
    }

    private function detectColumns(array $headers, int $headerRow = 1): array
    {
        $normalised = array_map(fn ($header): string => strtolower(trim((string) $header)), $headers);

        $mapping = ['header_row' => $headerRow, 'detected' => []];

        foreach (self::ALIASES as $role => $aliases) {
            $mapping[$role] = null;

            foreach ($aliases as $alias) {
                $index = array_search($alias, $normalised, true);

                if ($index !== false) {
                    $mapping[$role] = (int) $index;
                    $mapping['detected'][$role] = (string) $headers[$index];

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  array<string, mixed>  $mapping
     * @return array<string, list<array{id: int, code: string, name: string, latitude: float, longitude: float}>>
     */
    private function existingStations(array $rows, array $mapping): array
    {
        $names = [];

        foreach ($rows as $cells) {
            $name = $this->text($cells[$mapping['name']] ?? null);

            if ($name !== null) {
                $names[] = mb_strtolower($this->normaliseName($name));
            }
        }

        $names = array_values(array_unique($names));

        if ($names === []) {
            return [];
        }

        $index = [];

        foreach (array_chunk($names, 400) as $chunk) {
            $stations = MonitoringStation::withTrashed()
                ->whereIn(DB::raw('LOWER(name)'), $chunk)
                ->get(['id', 'code', 'name', 'latitude', 'longitude']);

            foreach ($stations as $station) {
                if ($station->latitude === null || $station->longitude === null) {
                    continue;
                }

                $index[mb_strtolower(trim((string) $station->name))][] = [
                    'id' => (int) $station->id,
                    'code' => (string) $station->code,
                    'name' => (string) $station->name,
                    'latitude' => (float) $station->latitude,
                    'longitude' => (float) $station->longitude,
                ];
            }
        }

        return $index;
    }

    /**
     * @return array<int, true>
     */
    private function existingCodes(string $prefix): array
    {
        return MonitoringStation::withTrashed()
            ->where('code', 'like', $prefix.'-%')
            ->pluck('code')
            ->mapWithKeys(fn (string $code): array => [strtoupper($code) => true])
            ->all();
    }

    /**
     * @param  array<int, true>  $existing
     * @param  array<int, true>  $generated
     */
    private function nextCode(?string $provided, ?int $sequence, int $fallback, string $prefix, array $existing, array &$generated, string $name): string
    {
        $candidate = $provided !== null ? strtoupper(trim($provided)) : null;

        if ($candidate === null || $candidate === '') {
            $number = $sequence ?? $fallback;
            $candidate = sprintf('%s-%04d', $prefix, $number);
        }

        $candidate = preg_replace('/\s+/', '-', $candidate) ?? $candidate;
        $base = $candidate;
        $suffix = 2;

        while (isset($existing[$candidate]) || isset($generated[$candidate])) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        $generated[$candidate] = true;

        return $candidate;
    }

    /**
     * @param  array<int, list<array{id: int, code: string, name: string, latitude: float, longitude: float}>>  $existing
     * @param  array{latitude: float, longitude: float}  $coordinates
     * @return array{id: int, code: string, name: string, distance_m: int}|null
     */
    private function findDuplicate(array $existing, string $name, array $coordinates): ?array
    {
        $candidates = $existing[mb_strtolower($name)] ?? [];

        if ($candidates === []) {
            return null;
        }

        $threshold = (float) config('stations.import.duplicate_distance_m', 1000);
        $nearest = null;

        foreach ($candidates as $candidate) {
            $distance = $this->coordinates->distanceMetres(
                $coordinates['latitude'],
                $coordinates['longitude'],
                $candidate['latitude'],
                $candidate['longitude'],
            );

            if ($distance <= $threshold && ($nearest === null || $distance < $nearest['distance_m'])) {
                $nearest = [...$candidate, 'distance_m' => (int) round($distance)];
            }
        }

        return $nearest;
    }

    /**
     * @param  list<mixed>  $cells
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $options
     * @param  list<array<string, mixed>>  $issues
     * @return array{latitude: float, longitude: float}|null
     */
    private function convertCoordinates(array $cells, array $mapping, array $options, array &$issues, ?bool &$projected): ?array
    {
        $easting = $mapping['easting'] !== null ? $this->numeric($cells[$mapping['easting']] ?? null) : null;
        $northing = $mapping['northing'] !== null ? $this->numeric($cells[$mapping['northing']] ?? null) : null;
        $first = $mapping['latitude'] !== null ? $this->numeric($cells[$mapping['latitude']] ?? null) : null;
        $second = $mapping['longitude'] !== null ? $this->numeric($cells[$mapping['longitude']] ?? null) : null;

        $mode = $options['coordinates'];

        if ($easting !== null && $northing !== null && $mode !== 'degrees') {
            $projected = true;

            return $this->coordinates->toGeographic($easting, $northing, (int) $options['utm_zone']);
        }

        if ($first === null || $second === null) {
            return null;
        }

        $isProjected = $mode === 'projected' || ($mode === 'auto' && $this->coordinates->looksProjected($first, $second));

        if ($isProjected) {
            $projected = true;
            [$eastingValue, $northingValue] = $this->coordinates->resolveAxes($first, $second, (int) $options['utm_zone']);

            return $this->coordinates->toGeographic($eastingValue, $northingValue, (int) $options['utm_zone']);
        }

        $projected = false;

        if (abs($first) > 90 || abs($second) > 180) {
            return null;
        }

        return ['latitude' => round($first, 7), 'longitude' => round($second, 7)];
    }

    private function stationTypeFor(?string $class): string
    {
        $definition = config('stations.classes.'.($class ?? 'other'));

        return is_array($definition) ? (string) $definition['station_type'] : 'meteorological';
    }

    public function normaliseClass(?string $value): ?string
    {
        $normalised = strtolower(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '');

        if ($normalised === '') {
            return null;
        }

        return match (true) {
            str_contains($normalised, 'synop') => 'synoptic',
            str_contains($normalised, 'princip') => 'principal',
            str_contains($normalised, 'precip'), str_contains($normalised, 'perc') => 'precipitation',
            str_contains($normalised, 'ordinar') => 'ordinary',
            default => 'other',
        };
    }

    private function normaliseName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    /**
     * @param  array{latitude: float, longitude: float}|null  $coordinates
     */
    private function duplicateKey(string $name, ?array $coordinates): string
    {
        return mb_strtolower($name).'|'.($coordinates === null ? '' : round($coordinates['latitude'], 4).','.round($coordinates['longitude'], 4));
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || is_bool($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(mixed $value): ?int
    {
        $numeric = $this->numeric($value);

        return $numeric === null ? null : (int) $numeric;
    }
}
