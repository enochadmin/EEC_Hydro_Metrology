<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;

/**
 * Content-based structure detection.
 *
 * Worksheet names are never consulted. The detector reads a bounded window of
 * cells and looks for the three shapes the application understands:
 *
 *  1. monthly records with day columns (Name/Elevation/Geogr1/Geogr2/element/
 *     Year/Month/Time followed by 1…31);
 *  2. a multi-row matrix (year row, month row, month-name row, day rows);
 *  3. a date/value series in two adjacent columns.
 *
 * A sheet can match more than one shape; the supplied second sheet matches
 * both the matrix and the date series, so both are returned and the user
 * decides which to transform.
 */
class ExcelStructureDetectionService
{
    public function __construct(private readonly ExcelFileService $files) {}

    /**
     * @return array{
     *     sheet: string,
     *     total_rows: int,
     *     total_columns: int,
     *     candidates: list<array<string, mixed>>,
     *     primary: string|null
     * }
     */
    public function detect(DataImportBatch $batch, string $sheetName): array
    {
        $info = $this->files->infoFor($batch, $sheetName);
        $rows = $this->files->readRows(
            $batch,
            $sheetName,
            1,
            min(40, max(1, $info['totalRows'])),
            null,
            null,
        );

        $candidates = [];

        $monthly = $this->detectMonthlyDailyColumns($rows);

        if ($monthly !== null) {
            $candidates[] = $monthly;
        }

        $matrix = $this->detectMatrix($rows);

        if ($matrix !== null) {
            $candidates[] = $matrix;
        }

        $daily = $this->detectDailySeries($rows);

        if ($daily !== null) {
            $candidates[] = $daily;
        }

        usort($candidates, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        $primary = null;

        foreach ($candidates as $candidate) {
            if ($candidate['key'] === 'multi_header_matrix') {
                $primary = $candidate['key'];
                break;
            }
        }

        $primary ??= $candidates[0]['key'] ?? null;

        return [
            'sheet' => $sheetName,
            'total_rows' => $info['totalRows'],
            'total_columns' => $info['totalColumns'],
            'candidates' => $candidates,
            'primary' => $primary,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function detectMonthlyDailyColumns(array $rows): ?array
    {
        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber > 8) {
                break;
            }

            $dayColumns = [];
            $normalised = [];

            foreach ($cells as $column => $value) {
                $normalised[$column] = $this->normalise($value);

                if (is_numeric($value)) {
                    $day = (int) $value;

                    if ($day >= 1 && $day <= 31 && (float) $value === (float) $day) {
                        $dayColumns[$day] = $column;
                    }
                }
            }

            if (count($dayColumns) < 20) {
                continue;
            }

            $columns = [];

            foreach (config('excel.monthly_columns') as $key => $aliases) {
                $columns[$key] = $this->findColumn($normalised, $aliases);
            }

            if (! $columns['station'] || ! $columns['year'] || ! $columns['month']) {
                continue;
            }

            ksort($dayColumns);

            $issues = [];

            foreach (['elevation', 'longitude', 'latitude', 'parameter', 'time'] as $optional) {
                if (! $columns[$optional]) {
                    $issues[] = 'No column matched "'.$optional.'"; it can be mapped manually.';
                }
            }

            $confidence = 0.6
                + ($columns['elevation'] ? 0.05 : 0)
                + ($columns['longitude'] ? 0.05 : 0)
                + ($columns['latitude'] ? 0.05 : 0)
                + ($columns['parameter'] ? 0.1 : 0)
                + ($columns['time'] ? 0.03 : 0)
                + (count($dayColumns) >= 28 ? 0.07 : 0);

            return [
                'key' => 'monthly_daily_columns',
                'label' => config('excel.structures.monthly_daily_columns'),
                'confidence' => round(min($confidence, 0.99), 2),
                'header_row' => (int) $rowNumber,
                'data_start_row' => (int) $rowNumber + 1,
                'headers' => $cells,
                'columns' => $columns,
                'day_columns' => $dayColumns,
                'day_column_count' => count($dayColumns),
                'issues' => $issues,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function detectMatrix(array $rows): ?array
    {
        $best = null;

        foreach ($rows as $rowNumber => $cells) {
            if ($rowNumber > 8) {
                break;
            }

            $yearCells = [];

            foreach ($cells as $column => $value) {
                if (is_numeric($value)) {
                    $year = (int) $value;

                    if ($year >= 1900 && $year <= 2200 && (float) $value === (float) $year) {
                        $yearCells[$column] = $year;
                    }
                }
            }

            if (count($yearCells) < 5) {
                continue;
            }

            $monthCells = [];

            foreach ($yearCells as $column => $year) {
                $monthValue = $rows[$rowNumber + 1][$column] ?? null;

                if (is_numeric($monthValue)) {
                    $month = (int) $monthValue;

                    if ($month >= 1 && $month <= 12 && (float) $monthValue === (float) $month) {
                        $monthCells[$column] = $month;
                    }
                }
            }

            if (count($monthCells) < 5) {
                continue;
            }

            $dayColumn = null;
            $dayNumbers = [];

            for ($columnIndex = 1; $columnIndex <= 10; $columnIndex++) {
                $letter = $this->files->columnLetter($columnIndex);
                $values = [];

                for ($offset = 0; $offset < 31; $offset++) {
                    $value = $rows[$rowNumber + 3 + $offset][$letter] ?? null;

                    if (! is_numeric($value)) {
                        $values = [];
                        break;
                    }

                    $values[] = (int) $value;
                }

                if ($values === range(1, 31)) {
                    $dayColumn = $letter;
                    $dayNumbers = $values;
                    break;
                }
            }

            if ($dayColumn === null) {
                continue;
            }

            $periods = [];

            foreach ($monthCells as $column => $month) {
                $year = $yearCells[$column];
                $periods[] = [
                    'column' => $column,
                    'year' => $year,
                    'month' => $month,
                    'label' => $this->monthLabel($year, $month),
                ];
            }

            $candidate = [
                'key' => 'multi_header_matrix',
                'label' => config('excel.structures.multi_header_matrix'),
                'confidence' => count($periods) >= 24 ? 0.97 : 0.75,
                'header_rows' => [(int) $rowNumber, (int) $rowNumber + 1, (int) $rowNumber + 2],
                'year_row' => (int) $rowNumber,
                'month_row' => (int) $rowNumber + 1,
                'month_name_row' => (int) $rowNumber + 2,
                'day_column' => $dayColumn,
                'day_rows' => [
                    'start' => (int) $rowNumber + 3,
                    'end' => (int) $rowNumber + 33,
                ],
                'periods' => $periods,
                'period_count' => count($periods),
                'issues' => [],
            ];

            if ($best === null || count($periods) > count($best['periods'])) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function detectDailySeries(array $rows): ?array
    {
        $maxColumn = 0;

        foreach ($rows as $cells) {
            $maxColumn = max($maxColumn, count($cells));
        }

        $maxColumn = min(max($maxColumn, 6), 12);

        for ($columnIndex = 1; $columnIndex < $maxColumn; $columnIndex++) {
            $dateColumn = $this->files->columnLetter($columnIndex);
            $valueColumn = $this->files->columnLetter($columnIndex + 1);

            $dateRows = 0;
            $valueRows = 0;
            $formulaRows = 0;
            $firstRow = null;
            $lastRow = null;

            foreach ($rows as $rowNumber => $cells) {
                $date = $cells[$dateColumn] ?? null;
                $value = $cells[$valueColumn] ?? null;

                if ($this->looksLikeDate($date)) {
                    $dateRows++;
                    $firstRow ??= $rowNumber;
                    $lastRow = $rowNumber;

                    if ($value !== null && $value !== '') {
                        $valueRows++;
                    }

                    if (is_string($value) && str_starts_with(trim($value), '=')) {
                        $formulaRows++;
                    }
                }
            }

            if ($dateRows < 3 || $valueRows < 3) {
                continue;
            }

            return [
                'key' => 'daily_series',
                'label' => config('excel.structures.daily_series'),
                'confidence' => $formulaRows > 0 ? 0.8 : 0.88,
                'date_column' => $dateColumn,
                'value_column' => $valueColumn,
                'start_row' => $firstRow,
                'sample_end_row' => $lastRow,
                'formula_based' => $formulaRows > 0,
                'issues' => $formulaRows > 0
                    ? ['The value column contains formulas; values mirror another region of the workbook and are not independent observations.']
                    : [],
            ];
        }

        return null;
    }

    private function looksLikeDate(mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (is_numeric($value)) {
            return (float) $value >= 20000 && (float) $value <= 80000;
        }

        if (! is_string($value)) {
            return false;
        }

        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', trim($value));
    }

    /**
     * @param  array<string, string|null>  $normalised
     * @param  list<string>  $aliases
     */
    private function findColumn(array $normalised, array $aliases): ?string
    {
        foreach ($normalised as $column => $value) {
            if ($value !== null && in_array($value, $aliases, true)) {
                return $column;
            }
        }

        return null;
    }

    private function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalised = preg_replace('/[^a-z0-9]/', '', strtolower(trim($value)));

        return $normalised === '' ? null : $normalised;
    }

    private function monthLabel(int $year, int $month): string
    {
        return \DateTimeImmutable::createFromFormat('!Y-n', $year.'-'.$month)?->format('M Y') ?? $year.'-'.$month;
    }
}
