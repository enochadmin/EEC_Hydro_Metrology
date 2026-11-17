<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Wide-to-long transformation for the three supported structures.
 *
 * Every produced row represents one calendar date. A row is `valid` only when
 * the cell held a number (zero included). Hyphens and empty cells become
 * `missing` rows with a null value; text and impossible dates become `invalid`
 * rows. Nothing is silently dropped and nothing is coerced to zero.
 */
class ExcelTransformationService
{
    public const STATUS_VALID = 'valid';

    public const STATUS_MISSING = 'missing';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        private readonly ExcelFileService $files,
        private readonly ExcelStructureDetectionService $detector,
    ) {}

    /**
     * @param  array<string, mixed>  $mapping
     * @return array{summary: array<string, mixed>, preview: list<array<string, mixed>>, path: string}
     */
    public function transform(DataImportBatch $batch, string $sheetName, string $structure, array $mapping): array
    {
        $info = $this->files->infoFor($batch, $sheetName);

        $path = 'excel/'.$batch->uuid.'/transformed-'.$structure.'.ndjson';
        Storage::disk('local')->makeDirectory('excel/'.$batch->uuid);

        $handle = fopen(Storage::disk('local')->path($path), 'wb');

        if ($handle === false) {
            throw new RuntimeException('The transformation output could not be opened.');
        }

        $summary = [
            'worksheet' => $sheetName,
            'structure_type' => $structure,
            'original_rows' => $info['totalRows'],
            'original_columns' => $info['totalColumns'],
            'valid_calendar_dates' => 0,
            'numeric_measurements' => 0,
            'zero_measurements' => 0,
            'missing_measurements' => 0,
            'invalid_records' => 0,
            'generated_observations' => 0,
            'rows_written' => 0,
            'formula_rows' => 0,
        ];

        $dates = [];
        $preview = [];

        $write = function (array $row) use ($handle, &$summary, &$preview): void {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
            $summary['rows_written']++;

            if ($row['status'] === self::STATUS_VALID) {
                $summary['generated_observations']++;
                $summary['numeric_measurements']++;

                if ((float) $row['value'] === 0.0) {
                    $summary['zero_measurements']++;
                }
            } elseif ($row['status'] === self::STATUS_MISSING) {
                $summary['missing_measurements']++;
            } else {
                $summary['invalid_records']++;
            }

            if (count($preview) < 120) {
                $preview[] = $row;
            }
        };

        $collectDate = function (array $row) use (&$dates): void {
            if (! empty($row['date'])) {
                $dates[$row['date']] = true;
            }
        };

        match ($structure) {
            'monthly_daily_columns' => $this->transformMonthly(
                $batch,
                $sheetName,
                $mapping,
                $write,
                $collectDate,
            ),
            'multi_header_matrix' => $this->transformMatrix(
                $batch,
                $sheetName,
                $mapping,
                $write,
                $collectDate,
            ),
            'daily_series' => $this->transformDaily(
                $batch,
                $sheetName,
                $mapping,
                $write,
                $collectDate,
            ),
            default => throw new RuntimeException('Unsupported structure "'.$structure.'".'),
        };

        fclose($handle);

        $summary['valid_calendar_dates'] = count($dates);

        $batch->forceFill([
            'sheet_name' => $sheetName,
            'structure_type' => $structure,
            'mapping' => $mapping,
            'summary' => $summary,
            'comparison' => null,
            'transformed_path' => $path,
            'original_rows' => $info['totalRows'],
            'original_columns' => $info['totalColumns'],
            'stage' => 'previewed',
        ])->save();

        return ['summary' => $summary, 'preview' => $preview, 'path' => $path];
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function transformMonthly(
        DataImportBatch $batch,
        string $sheetName,
        array $mapping,
        callable $write,
        callable $collectDate,
    ): void {
        $rows = $this->files->readWholeSheet($batch, $sheetName);

        $columns = $mapping['columns'] ?? [];
        $dayColumns = $mapping['day_columns'] ?? [];
        $headerRow = (int) ($mapping['header_row'] ?? 1);

        if (! isset($columns['station'], $columns['year'], $columns['month']) || $dayColumns === []) {
            throw new RuntimeException('The mapping is incomplete: station, year, month and day columns are required.');
        }

        $startRow = (int) ($mapping['data_start_row'] ?? $headerRow + 1);

        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber < $startRow) {
                continue;
            }

            $station = $this->text($cells[$columns['station']] ?? null);
            $year = $this->integer($cells[$columns['year']] ?? null);
            $month = $this->integer($cells[$columns['month']] ?? null);
            $time = $this->time($cells[$columns['time'] ?? ''] ?? null);
            $parameterRaw = $this->text($cells[$columns['parameter'] ?? ''] ?? null) ?? 'Rainfall';
            $parameterCode = $mapping['parameter_code'] ?? $this->parameterCode($parameterRaw);
            $elevation = $this->number($cells[$columns['elevation'] ?? ''] ?? null);
            $longitude = $this->coordinate($cells[$columns['longitude'] ?? ''] ?? null);
            $latitude = $this->coordinate($cells[$columns['latitude'] ?? ''] ?? null);

            if ($station === null) {
                $write($this->invalidRow($rowNumber, null, null, $parameterRaw, $parameterCode, 'MISSING_STATION', 'The row has no station name.'));

                continue;
            }

            if ($year === null || $month === null || ! checkdate($month, 1, $year)) {
                $write($this->invalidRow($rowNumber, $station, null, $parameterRaw, $parameterCode, 'INVALID_PERIOD', 'Year/month are missing or outside the calendar.'));

                continue;
            }

            $daysInMonth = CarbonImmutable::create($year, $month, 1)->daysInMonth;

            foreach ($dayColumns as $day => $column) {
                $day = (int) $day;

                if ($day < 1 || $day > $daysInMonth) {
                    continue;
                }

                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $raw = $cells[$column] ?? null;
                [$status, $value, $flag, $message] = $this->classify($raw);

                $row = [
                    'row_number' => $rowNumber,
                    'station_name' => $station,
                    'elevation' => $elevation,
                    'longitude' => $longitude,
                    'latitude' => $latitude,
                    'parameter' => $parameterRaw,
                    'parameter_code' => $parameterCode,
                    'date' => $date,
                    'time' => $time,
                    'value' => $value,
                    'source_value' => $raw,
                    'status' => $status,
                    'flag' => $flag,
                    'message' => $message,
                    'source_cell' => $column.$rowNumber,
                    'source_sheet' => $sheetName,
                ];

                $collectDate($row);
                $write($row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function transformMatrix(
        DataImportBatch $batch,
        string $sheetName,
        array $mapping,
        callable $write,
        callable $collectDate,
    ): void {
        $matrix = null;

        if (! isset($mapping['periods'], $mapping['day_rows']['start'])) {
            $detected = $this->detector->detect($batch, $sheetName);

            foreach ($detected['candidates'] as $candidate) {
                if ($candidate['key'] === 'multi_header_matrix') {
                    $matrix = $candidate;
                    break;
                }
            }

            if ($matrix === null) {
                throw new RuntimeException('The sheet no longer matches the multi-header matrix structure.');
            }
        }

        $periods = $mapping['periods'] ?? $matrix['periods'];
        $dayRowStart = (int) ($mapping['day_rows']['start'] ?? $matrix['day_rows']['start']);
        $dayColumn = $mapping['day_column'] ?? $matrix['day_column'];

        $rows = $this->files->readRows($batch, $sheetName, 1, 40, null, null);

        $station = $this->text($mapping['station_name'] ?? null) ?? $this->stationFromSheet($sheetName);
        $parameterRaw = $this->text($mapping['parameter_label'] ?? null) ?? 'Rainfall';
        $parameterCode = $mapping['parameter_code'] ?? $this->parameterCode($parameterRaw);
        $time = $this->time($mapping['time'] ?? null);

        foreach ($periods as $period) {
            $year = (int) ($period['year'] ?? 0);
            $month = (int) ($period['month'] ?? 0);
            $column = $period['column'] ?? null;

            if ($column === null || $year === 0 || $month === 0 || ! checkdate($month, 1, $year)) {
                continue;
            }

            $daysInMonth = CarbonImmutable::create($year, $month, 1)->daysInMonth;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $sourceRow = $dayRowStart + $day - 1;
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $raw = $rows[$sourceRow][$column] ?? null;
                [$status, $value, $flag, $message] = $this->classify($raw);

                $row = [
                    'row_number' => $sourceRow,
                    'station_name' => $station,
                    'elevation' => null,
                    'longitude' => null,
                    'latitude' => null,
                    'parameter' => $parameterRaw,
                    'parameter_code' => $parameterCode,
                    'date' => $date,
                    'time' => $time,
                    'value' => $value,
                    'source_value' => $raw,
                    'status' => $status,
                    'flag' => $flag,
                    'message' => $message,
                    'source_cell' => $column.$sourceRow,
                    'source_sheet' => $sheetName,
                ];

                $collectDate($row);
                $write($row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function transformDaily(
        DataImportBatch $batch,
        string $sheetName,
        array $mapping,
        callable $write,
        callable $collectDate,
    ): void {
        $dateColumn = $mapping['date_column'] ?? 'A';
        $valueColumn = $mapping['value_column'] ?? 'B';

        $series = $this->files->readDailyColumns($batch, $sheetName, $dateColumn, $valueColumn);

        $station = $this->text($mapping['station_name'] ?? null) ?? $this->stationFromSheet($sheetName);
        $parameterRaw = $this->text($mapping['parameter_label'] ?? null) ?? 'Rainfall';
        $parameterCode = $mapping['parameter_code'] ?? $this->parameterCode($parameterRaw);
        $time = $this->time($mapping['time'] ?? null);

        $matrixValues = $this->matrixValueMap($batch, $sheetName);

        foreach ($series as $rowNumber => $entry) {
            $date = $this->date($entry['date']);

            if ($date === null) {
                $write([
                    'row_number' => $rowNumber,
                    'station_name' => $station,
                    'elevation' => null,
                    'longitude' => null,
                    'latitude' => null,
                    'parameter' => $parameterRaw,
                    'parameter_code' => $parameterCode,
                    'date' => null,
                    'time' => $time,
                    'value' => null,
                    'source_value' => $entry['date'],
                    'status' => self::STATUS_INVALID,
                    'flag' => 'INVALID_DATE',
                    'message' => 'The date cell is empty or not a calendar date.',
                    'source_cell' => $dateColumn.$rowNumber,
                    'source_sheet' => $sheetName,
                ]);

                continue;
            }

            $raw = $entry['value'];

            if ($entry['formula'] !== null) {
                $resolved = $this->resolveFormulaValue($entry['formula'], $matrixValues);

                if (! $resolved['ok']) {
                    $write([
                        'row_number' => $rowNumber,
                        'station_name' => $station,
                        'elevation' => null,
                        'longitude' => null,
                        'latitude' => null,
                        'parameter' => $parameterRaw,
                        'parameter_code' => $parameterCode,
                        'date' => $date->format('Y-m-d'),
                        'time' => $time,
                        'value' => null,
                        'source_value' => $raw,
                        'status' => self::STATUS_INVALID,
                        'flag' => $resolved['flag'],
                        'message' => $resolved['message'],
                        'source_cell' => $valueColumn.$rowNumber,
                        'source_sheet' => $sheetName,
                    ]);

                    continue;
                }

                $raw = $resolved['value'];
            }

            [$status, $value, $flag, $message] = $this->classify($raw);

            $row = [
                'row_number' => $rowNumber,
                'station_name' => $station,
                'elevation' => null,
                'longitude' => null,
                'latitude' => null,
                'parameter' => $parameterRaw,
                'parameter_code' => $parameterCode,
                'date' => $date->format('Y-m-d'),
                'time' => $time,
                'value' => $value,
                'source_value' => $entry['value'],
                'status' => $status,
                'flag' => $flag,
                'message' => $message,
                'source_cell' => $valueColumn.$rowNumber,
                'source_sheet' => $sheetName,
            ];

            $collectDate($row);
            $write($row);
        }
    }

    /**
     * Resolve `=D4`-style formulas against the matrix instead of recalculating
     * the workbook. Returns the referenced cell's raw value.
     *
     * @param  array<string, mixed>  $matrixValues
     * @return array{ok: bool, value: mixed, flag: string, message: string}
     */
    private function resolveFormulaValue(string $formula, array $matrixValues): array
    {
        if (! preg_match('/^=\s*([A-Z]{1,3})(\d+)$/', $formula, $matches)) {
            return ['ok' => false, 'value' => null, 'flag' => 'UNSUPPORTED_FORMULA', 'message' => 'The cell formula cannot be resolved to a single matrix cell.'];
        }

        $coordinate = $matches[1].$matches[2];

        if (! array_key_exists($coordinate, $matrixValues)) {
            return ['ok' => false, 'value' => null, 'flag' => 'UNRESOLVED_FORMULA', 'message' => 'The formula points outside the monthly matrix.'];
        }

        return ['ok' => true, 'value' => $matrixValues[$coordinate], 'flag' => '', 'message' => ''];
    }

    /**
     * @return array<string, mixed>
     */
    public function matrixValueMap(DataImportBatch $batch, string $sheetName): array
    {
        $rows = $this->files->readRows($batch, $sheetName, 1, 34, null, null);
        $map = [];

        foreach ($rows as $rowNumber => $cells) {
            foreach ($cells as $column => $value) {
                $map[$column.$rowNumber] = $value;
            }
        }

        return $map;
    }

    /**
     * @return array{0: string, 1: float|null, 2: string|null, 3: string|null}
     */
    public function classify(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [self::STATUS_MISSING, null, 'empty', 'No value was recorded for this date.'];
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);

            if ($trimmed === '' || $trimmed === '-' || $trimmed === '—' || strtolower($trimmed) === 'na' || strtolower($trimmed) === 'n/a') {
                return [self::STATUS_MISSING, null, 'missing_marker', 'The source marks this date as missing.'];
            }

            if (str_starts_with($trimmed, '=')) {
                return [self::STATUS_INVALID, null, 'FORMULA_NOT_EVALUATED', 'The cell is a formula; it cannot be imported as a measurement.'];
            }

            $cleaned = str_replace([',', ' '], '', $trimmed);

            if (is_numeric($cleaned)) {
                return [self::STATUS_VALID, (float) $cleaned, null, null];
            }

            return [self::STATUS_INVALID, null, 'NON_NUMERIC', 'The value "'.mb_substr($trimmed, 0, 40).'" is not numeric.'];
        }

        if (is_int($raw) || is_float($raw)) {
            return [self::STATUS_VALID, (float) $raw, null, null];
        }

        return [self::STATUS_INVALID, null, 'UNSUPPORTED_VALUE', 'The cell contains an unsupported value type.'];
    }

    public function date(mixed $value): ?CarbonImmutable
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }

            if (is_numeric($value)) {
                $number = (float) $value;

                if ($number >= 20000 && $number <= 80000) {
                    return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($number));
                }

                return null;
            }

            if (! is_string($value)) {
                return null;
            }

            $value = trim($value);

            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date;
                }
            }

            if (preg_match('/^\\d{1,4}[-\\/]\\d{1,2}[-\\/]\\d{1,4}$/', $value)) {
                return null;
            }

            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function parameterCode(string $raw): string
    {
        $normalised = preg_replace('/[^a-z0-9]/', '', strtolower($raw)) ?? '';

        return config('excel.element_aliases.'.$normalised, $normalised);
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    private function coordinate(mixed $value): ?float
    {
        return $this->number($value);
    }

    private function integer(mixed $value): ?int
    {
        $number = $this->number($value);

        return $number === null ? null : (int) $number;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function time(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '09:00';
        }

        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $seconds = (int) round((float) $value * 86400);

            return sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $matches)) {
            return sprintf('%02d:%02d', min(23, (int) $matches[1]), min(59, (int) $matches[2]));
        }

        return '09:00';
    }

    private function stationFromSheet(string $sheetName): string
    {
        $name = preg_replace('/\b(raw\s*data|column|data|sheet)\b/i', ' ', $sheetName) ?? $sheetName;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name === '' ? $sheetName : $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidRow(int $row, ?string $station, ?string $date, string $parameter, string $code, string $flag, string $message): array
    {
        return [
            'row_number' => $row,
            'station_name' => $station,
            'elevation' => null,
            'longitude' => null,
            'latitude' => null,
            'parameter' => $parameter,
            'parameter_code' => $code,
            'date' => $date,
            'time' => '09:00',
            'value' => null,
            'status' => self::STATUS_INVALID,
            'flag' => $flag,
            'message' => $message,
            'source_cell' => null,
            'source_sheet' => null,
        ];
    }
}
