<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Carbon\CarbonImmutable;

/**
 * Compares the matrix region of a sheet with its date/value series.
 *
 * The supplied workbook's column B is a chain of formulas (`=D4`, `=D5`…) that
 * mirrors the matrix. The comparison therefore resolves each formula to the
 * matrix cell it points at, checks that the referenced cell's calendar date
 * matches the date in column A, and only then compares values. This exposes
 * alignment errors instead of trusting the flattened series.
 */
class ExcelComparisonService
{
    public function __construct(
        private readonly ExcelFileService $files,
        private readonly ExcelStructureDetectionService $detector,
        private readonly ExcelTransformationService $transformer,
    ) {}

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed>
     */
    public function compare(DataImportBatch $batch, string $sheetName, array $mapping = []): array
    {
        $detected = $this->detector->detect($batch, $sheetName);
        $matrix = null;
        $daily = null;

        foreach ($detected['candidates'] as $candidate) {
            if ($candidate['key'] === 'multi_header_matrix') {
                $matrix = $candidate;
            }

            if ($candidate['key'] === 'daily_series') {
                $daily = $candidate;
            }
        }

        if ($matrix === null || $daily === null) {
            $result = [
                'available' => false,
                'message' => 'Comparison needs both a multi-header matrix and a date/value series on the same sheet.',
                'compared_at' => now()->toIso8601String(),
            ];

            $batch->forceFill(['comparison' => $result, 'stage' => 'compared'])->save();

            return $result;
        }

        $periods = $mapping['periods'] ?? $matrix['periods'];
        $dayRowStart = (int) ($mapping['day_rows']['start'] ?? $matrix['day_rows']['start']);
        $dayColumn = $mapping['day_column'] ?? $matrix['day_column'];

        $matrixRows = $this->files->readRows($batch, $sheetName, 1, 40, null, null);

        $periodByColumn = [];

        foreach ($periods as $period) {
            $periodByColumn[$period['column']] = $period;
        }

        $matrixMap = [];
        $matrixValid = 0;
        $matrixMissing = 0;
        $matrixInvalid = 0;

        foreach ($periods as $period) {
            $year = (int) $period['year'];
            $month = (int) $period['month'];
            $column = $period['column'];

            if (! checkdate($month, 1, $year)) {
                continue;
            }

            $daysInMonth = CarbonImmutable::create($year, $month, 1)->daysInMonth;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $row = $dayRowStart + $day - 1;
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $raw = $matrixRows[$row][$column] ?? null;
                [$status, $value] = $this->transformer->classify($raw);

                $matrixMap[$date] = ['value' => $value, 'status' => $status, 'cell' => $column.$row];

                if ($status === 'valid') {
                    $matrixValid++;
                } elseif ($status === 'missing') {
                    $matrixMissing++;
                } else {
                    $matrixInvalid++;
                }
            }
        }

        $series = $this->files->readDailyColumns(
            $batch,
            $sheetName,
            $daily['date_column'],
            $daily['value_column'],
        );

        $dailyMap = [];
        $dailyValid = 0;
        $dailyMissing = 0;
        $dailyInvalid = 0;
        $formulaRows = 0;
        $alignmentErrors = [];
        $valueDifferences = [];

        foreach ($series as $rowNumber => $entry) {
            $date = $this->transformer->date($entry['date']);

            if ($date === null) {
                $dailyInvalid++;

                continue;
            }

            $dateKey = $date->format('Y-m-d');
            $raw = $entry['value'];
            $formula = $entry['formula'];

            if ($formula !== null) {
                $formulaRows++;

                if (preg_match('/^=\s*([A-Z]{1,3})(\d+)$/', $formula, $matches)) {
                    $column = $matches[1];
                    $row = (int) $matches[2];
                    $period = $periodByColumn[$column] ?? null;
                    $day = $row - $dayRowStart + 1;

                    if ($period === null || $day < 1 || $day > 31) {
                        $alignmentErrors[] = [
                            'date' => $dateKey,
                            'row' => $rowNumber,
                            'formula' => $formula,
                            'message' => 'The formula points outside the monthly matrix.',
                        ];

                        continue;
                    }

                    $expected = sprintf('%04d-%02d-%02d', (int) $period['year'], (int) $period['month'], $day);

                    if ($expected !== $dateKey) {
                        $alignmentErrors[] = [
                            'date' => $dateKey,
                            'row' => $rowNumber,
                            'formula' => $formula,
                            'expected_date' => $expected,
                            'message' => 'The date in column A does not match the matrix cell referenced by the formula.',
                        ];
                    }

                    $raw = $matrixRows[$row][$column] ?? null;
                } else {
                    $alignmentErrors[] = [
                        'date' => $dateKey,
                        'row' => $rowNumber,
                        'formula' => $formula,
                        'message' => 'The formula cannot be resolved to a matrix cell.',
                    ];

                    continue;
                }
            }

            [$status, $value] = $this->transformer->classify($raw);

            $dailyMap[$dateKey] = ['value' => $value, 'status' => $status, 'row' => $rowNumber];

            if ($status === 'valid') {
                $dailyValid++;
            } elseif ($status === 'missing') {
                $dailyMissing++;
            } else {
                $dailyInvalid++;
            }
        }

        $matching = 0;
        $different = 0;
        $missingInDaily = 0;
        $missingInMatrix = 0;
        $missingValues = 0;

        foreach ($matrixMap as $date => $matrixEntry) {
            if (! isset($dailyMap[$date])) {
                $missingInDaily++;

                continue;
            }

            $dailyEntry = $dailyMap[$date];

            if ($matrixEntry['status'] === 'valid' && $dailyEntry['status'] === 'valid') {
                if (abs((float) $matrixEntry['value'] - (float) $dailyEntry['value']) <= 0.000001) {
                    $matching++;
                } else {
                    $different++;

                    if (count($valueDifferences) < 100) {
                        $valueDifferences[] = [
                            'date' => $date,
                            'matrix' => $matrixEntry['value'],
                            'daily' => $dailyEntry['value'],
                            'matrix_cell' => $matrixEntry['cell'],
                            'daily_row' => $dailyEntry['row'],
                        ];
                    }
                }
            } elseif ($matrixEntry['status'] !== 'valid' || $dailyEntry['status'] !== 'valid') {
                $missingValues++;
            }
        }

        foreach ($dailyMap as $date => $entry) {
            if (! isset($matrixMap[$date])) {
                $missingInMatrix++;
            }
        }

        $result = [
            'available' => true,
            'compared_at' => now()->toIso8601String(),
            'sheet' => $sheetName,
            'matrix' => [
                'dates' => count($matrixMap),
                'valid' => $matrixValid,
                'missing' => $matrixMissing,
                'invalid' => $matrixInvalid,
            ],
            'daily' => [
                'dates' => count($dailyMap),
                'valid' => $dailyValid,
                'missing' => $dailyMissing,
                'invalid' => $dailyInvalid,
                'formula_rows' => $formulaRows,
            ],
            'matching' => $matching,
            'different' => $different,
            'missing_in_daily' => $missingInDaily,
            'missing_in_matrix' => $missingInMatrix,
            'missing_values' => $missingValues,
            'alignment_errors' => count($alignmentErrors),
            'alignment_samples' => array_slice($alignmentErrors, 0, 50),
            'value_differences' => $valueDifferences,
            'formula_mirror' => $formulaRows > 0,
            'note' => $formulaRows > 0
                ? 'The date/value series is formula-linked to the matrix; differences reflect blank/missing handling, not independent measurements.'
                : 'The date/value series is independent of the matrix and was compared cell by cell.',
        ];

        $batch->forceFill(['comparison' => $result, 'stage' => 'compared'])->save();

        return $result;
    }
}
