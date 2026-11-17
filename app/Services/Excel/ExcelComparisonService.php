<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Compares generated observation rows with the source workbook cells recorded
 * during transformation.
 */
class ExcelComparisonService
{
    private const CHANGE_SAMPLE_LIMIT = 150;

    public function __construct(private readonly ExcelTransformationService $transformer) {}

    /**
     * @return array<string, mixed>
     */
    public function compare(DataImportBatch $batch): array
    {
        if (! $batch->transformed_path) {
            throw new RuntimeException('Generate the transformed dataset before comparing it with the source workbook.');
        }

        $path = Storage::disk('local')->path($batch->transformed_path);

        if (! is_file($path)) {
            throw new RuntimeException('The generated dataset is missing from private storage. Regenerate the transformation and compare again.');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The generated dataset could not be read for comparison.');
        }

        $counts = [
            'total' => 0,
            'preserved' => 0,
            'changed' => 0,
            'missing' => 0,
            'invalid' => 0,
            'formulas_resolved' => 0,
        ];
        $changes = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);

                if (! is_array($row)) {
                    continue;
                }

                $counts['total']++;
                $sourceValue = $row['source_value'] ?? null;
                $sourceValueAvailable = array_key_exists('source_value', $row);
                $generatedStatus = (string) ($row['status'] ?? 'invalid');
                $generatedValue = $row['value'] ?? null;
                $changeType = $this->changeType(
                    $sourceValue,
                    $sourceValueAvailable,
                    $generatedStatus,
                    $generatedValue,
                );

                $counts[$changeType]++;

                if ($changeType === 'preserved') {
                    continue;
                }

                if (count($changes) < self::CHANGE_SAMPLE_LIMIT) {
                    $changes[] = [
                        'change_type' => $changeType,
                        'source_cell' => $row['source_cell'] ?? null,
                        'source_value' => $sourceValue,
                        'date' => $row['date'] ?? null,
                        'station' => $row['station_name'] ?? null,
                        'parameter' => $row['parameter'] ?? null,
                        'generated_value' => $generatedValue,
                        'generated_status' => $generatedStatus,
                        'message' => $row['message'] ?? null,
                    ];
                }
            }
        } finally {
            fclose($handle);
        }

        $result = [
            'available' => true,
            'compared_at' => now()->toIso8601String(),
            'source_sheet' => $batch->sheet_name,
            'generated_structure' => $batch->structure_type,
            'counts' => $counts,
            'changes' => $changes,
            'changes_truncated' => ($counts['total'] - $counts['preserved']) > self::CHANGE_SAMPLE_LIMIT,
        ];

        $batch->forceFill(['comparison' => $result, 'stage' => 'compared'])->save();

        return $result;
    }

    private function changeType(mixed $sourceValue, bool $sourceValueAvailable, string $generatedStatus, mixed $generatedValue): string
    {
        if (! $sourceValueAvailable) {
            return 'invalid';
        }

        if (is_string($sourceValue) && str_starts_with(trim($sourceValue), '=')) {
            return $generatedStatus === ExcelTransformationService::STATUS_VALID
                ? 'formulas_resolved'
                : 'changed';
        }

        [$sourceStatus, $sourceNumericValue] = $this->transformer->classify($sourceValue);

        if ($sourceStatus !== $generatedStatus) {
            return 'changed';
        }

        if ($generatedStatus === ExcelTransformationService::STATUS_VALID) {
            return abs((float) $sourceNumericValue - (float) $generatedValue) <= 0.000001
                ? 'preserved'
                : 'changed';
        }

        return match ($generatedStatus) {
            ExcelTransformationService::STATUS_MISSING => 'missing',
            ExcelTransformationService::STATUS_INVALID => 'invalid',
            default => 'changed',
        };
    }
}
