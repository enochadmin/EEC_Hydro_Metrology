<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use RuntimeException;

class ExcelStationImportPreparationService
{
    public function __construct(
        private readonly ExcelFileService $files,
        private readonly ExcelStructureDetectionService $detector,
        private readonly ExcelTransformationService $transformer,
        private readonly ExcelValidationService $validation,
    ) {}

    public function prepare(DataImportBatch $batch): bool
    {
        $sheet = $this->files->sheetNames($batch)[0] ?? null;

        if ($sheet === null) {
            throw new RuntimeException('The workbook has no worksheets.');
        }

        $detection = $this->detector->detect($batch, $sheet);
        $structure = $detection['primary'];

        if ($structure === null) {
            return false;
        }

        $candidate = collect($detection['candidates'])->firstWhere('key', $structure);

        if ($candidate === null) {
            return false;
        }

        $station = $batch->station;
        $stationParameters = $station?->stationParameters()
            ->where('is_active', true)
            ->with('parameter:id,code,name')
            ->get()
            ->pluck('parameter')
            ->filter()
            ->values() ?? collect();
        $parameterIsInWorkbook = $structure === 'monthly_daily_columns'
            && ! empty($candidate['columns']['parameter']);

        if (! $parameterIsInWorkbook && $stationParameters->count() !== 1) {
            return false;
        }

        $parameter = $parameterIsInWorkbook ? null : $stationParameters->first();
        $mapping = $this->mappingFromCandidate(
            $candidate,
            $station?->name ?? '',
            $parameter?->code,
            $parameter?->name,
        );
        $this->transformer->transform($batch, $sheet, $structure, $mapping);
        $this->validation->validate($batch->refresh());

        return true;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function mappingFromCandidate(
        array $candidate,
        string $stationName,
        ?string $parameterCode,
        ?string $parameterName,
    ): array {
        if ($candidate['key'] === 'monthly_daily_columns') {
            $mapping = [
                'header_row' => $candidate['header_row'],
                'data_start_row' => $candidate['data_start_row'],
                'columns' => $candidate['columns'],
                'day_columns' => $candidate['day_columns'],
                'station_name' => null,
                'time' => '09:00',
            ];

            if (empty($candidate['columns']['parameter']) && $parameterCode !== null) {
                $mapping['parameter_code'] = $parameterCode;
            }

            return $mapping;
        }

        if ($candidate['key'] === 'multi_header_matrix') {
            return [
                'header_rows' => $candidate['header_rows'],
                'year_row' => $candidate['year_row'],
                'month_row' => $candidate['month_row'],
                'month_name_row' => $candidate['month_name_row'],
                'day_column' => $candidate['day_column'],
                'day_rows' => $candidate['day_rows'],
                'periods' => $candidate['periods'],
                'station_name' => $stationName,
                'parameter_label' => $parameterName,
                'parameter_code' => $parameterCode,
                'time' => '09:00',
            ];
        }

        return [
            'date_column' => $candidate['date_column'],
            'value_column' => $candidate['value_column'],
            'start_row' => $candidate['start_row'],
            'station_name' => $stationName,
            'parameter_label' => $parameterName,
            'parameter_code' => $parameterCode,
            'time' => '09:00',
        ];
    }
}
