<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Exports the transformed dataset and the validation findings.
 *
 * Both files are generated on the private disk and streamed with
 * `deleteFileAfterSend`, so repeated downloads never leak temporary copies.
 */
class ExcelExportService
{
    public function __construct(private readonly ExcelValidationService $validation) {}

    /**
     * @return array{path: string, name: string}
     */
    public function transformedWorkbook(DataImportBatch $batch): array
    {
        if (! $batch->transformed_path) {
            throw new RuntimeException('There is no transformed dataset to export.');
        }

        $source = Storage::disk('local')->path($batch->transformed_path);

        if (! is_file($source)) {
            throw new RuntimeException('The transformed dataset is missing.');
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('EEC-BHDMS')
            ->setTitle('Transformed observations')
            ->setDescription('Wide-to-long transformation of '.$batch->file_name);

        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Observations');
        $data->fromArray([
            'Station',
            'Elevation',
            'Longitude',
            'Latitude',
            'Parameter',
            'Date',
            'Time',
            'Value',
            'Status',
            'Flag',
            'Source cell',
        ], null, 'A1');

        $handle = fopen($source, 'rb');
        $row = 2;

        while (($line = fgets($handle)) !== false) {
            $record = json_decode(trim($line), true);

            if (! is_array($record)) {
                continue;
            }

            $data->fromArray([
                $record['station_name'] ?? null,
                $record['elevation'] ?? null,
                $record['longitude'] ?? null,
                $record['latitude'] ?? null,
                $record['parameter'] ?? null,
                $record['date'] ?? null,
                $record['time'] ?? null,
                $record['value'] ?? null,
                $record['status'] ?? null,
                $record['flag'] ?? null,
                $record['source_cell'] ?? null,
            ], null, 'A'.$row);

            $row++;
        }

        fclose($handle);

        $data->freezePane('A2');
        $data->getStyle('A1:K1')->getFont()->setBold(true);

        foreach (range('A', 'K') as $column) {
            $data->getColumnDimension($column)->setAutoSize(true);
        }

        $summary = $spreadsheet->createSheet();
        $summary->setTitle('Summary');
        $summary->fromArray(['Metric', 'Value'], null, 'A1');
        $summary->getStyle('A1:B1')->getFont()->setBold(true);

        $line = 2;

        foreach (($batch->summary ?? []) as $key => $value) {
            $summary->fromArray([str_replace('_', ' ', ucfirst($key)), is_scalar($value) ? $value : json_encode($value)], null, 'A'.$line);
            $line++;
        }

        $summary->getColumnDimension('A')->setWidth(32);
        $summary->getColumnDimension('B')->setWidth(40);

        $path = 'excel/'.$batch->uuid.'/exports/transformed-'.$batch->uuid.'.xlsx';
        Storage::disk('local')->makeDirectory(dirname($path));
        $absolute = Storage::disk('local')->path($path);

        (new Xlsx($spreadsheet))->save($absolute);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $absolute,
            'name' => 'transformed-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $batch->file_name).'.xlsx',
        ];
    }

    /**
     * Export the normalized observations as a calendar matrix, with one sheet
     * for each station and parameter combination.
     *
     * @return array{path: string, name: string}
     */
    public function columnBasedWorkbook(DataImportBatch $batch): array
    {
        if (! $batch->transformed_path) {
            throw new RuntimeException('Transform the workbook before exporting the column-based format.');
        }

        $source = Storage::disk('local')->path($batch->transformed_path);

        if (! is_file($source)) {
            throw new RuntimeException('The transformed dataset is missing.');
        }

        $handle = fopen($source, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The transformed dataset could not be opened.');
        }

        $groups = [];
        $unmappedRows = [];

        while (($line = fgets($handle)) !== false) {
            $record = json_decode(trim($line), true);

            if (! is_array($record)) {
                continue;
            }

            $station = trim((string) ($record['station_name'] ?? ''));
            $parameter = trim((string) ($record['parameter_code'] ?? ''));
            $date = $record['date'] ?? null;

            if ($station === '' || $parameter === '' || ! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $unmappedRows[] = $record;

                continue;
            }

            $key = json_encode([$station, $parameter], JSON_UNESCAPED_UNICODE);
            $groups[$key] ??= [
                'station' => $station,
                'parameter' => trim((string) ($record['parameter'] ?? $parameter)),
                'parameter_code' => $parameter,
                'elevation' => $record['elevation'] ?? null,
                'longitude' => $record['longitude'] ?? null,
                'latitude' => $record['latitude'] ?? null,
                'records' => [],
            ];
            foreach (['elevation', 'longitude', 'latitude'] as $metadataField) {
                if ($groups[$key][$metadataField] === null && ($record[$metadataField] ?? null) !== null) {
                    $groups[$key][$metadataField] = $record[$metadataField];
                }
            }
            $groups[$key]['records'][] = $record;
        }

        fclose($handle);

        if ($groups === []) {
            throw new RuntimeException('There are no dated station measurements to export.');
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('EEC-BHDMS')
            ->setTitle('Column-based hydrological data')
            ->setDescription('Calendar matrix generated from '.$batch->file_name);

        $index = $spreadsheet->getActiveSheet();
        $index->setTitle('Index');
        $index->fromArray([
            ['Station', 'Parameter', 'Parameter code', 'Elevation', 'Longitude', 'Latitude', 'First date', 'Last date', 'Measurements', 'Matrix periods'],
        ], null, 'A1');
        $index->getStyle('A1:J1')->getFont()->setBold(true);

        foreach (range('A', 'J') as $column) {
            $index->getColumnDimension($column)->setAutoSize(true);
        }

        $sheetNames = [];
        $stationGroupCounts = [];

        foreach ($groups as $group) {
            $stationGroupCounts[$group['station']] = ($stationGroupCounts[$group['station']] ?? 0) + 1;
        }

        $indexRow = 2;
        $invalidRows = $unmappedRows;

        foreach ($groups as $group) {
            $records = $group['records'];
            usort($records, static fn (array $left, array $right): int => [$left['date'], (int) ($left['row_number'] ?? 0)] <=> [$right['date'], (int) ($right['row_number'] ?? 0)]);

            $dateOccurrences = [];
            $periodOccurrences = [];
            $firstMonth = null;
            $lastMonth = null;
            $measurements = [];

            foreach ($records as $record) {
                $date = $record['date'];
                $month = substr($date, 0, 7);
                $occurrence = ($dateOccurrences[$date] ?? 0) + 1;
                $dateOccurrences[$date] = $occurrence;
                $record['occurrence'] = $occurrence;
                $measurements[] = $record;
                $periodOccurrences[$month] = max($periodOccurrences[$month] ?? 0, $occurrence);
                $firstMonth ??= $month;
                $lastMonth = $month;
            }

            $periods = [];
            $month = $firstMonth;

            while ($month !== null && $month <= $lastMonth) {
                $occurrences = max(1, $periodOccurrences[$month] ?? 0);

                for ($occurrence = 1; $occurrence <= $occurrences; $occurrence++) {
                    $periods[] = ['month' => $month, 'occurrence' => $occurrence];
                }

                $month = date('Y-m', strtotime($month.'-01 +1 month'));
            }

            if (count($periods) > 16381) {
                throw new RuntimeException('The selected date range is too wide for an Excel worksheet.');
            }

            $sheetTitle = $group['station'].' column';

            if ($stationGroupCounts[$group['station']] > 1) {
                $sheetTitle .= ' '.$group['parameter_code'];
            }

            $sheetTitle = preg_replace('/[\\\\\/*?:\[\]]/', ' ', $sheetTitle) ?? 'Column data';
            $sheetTitle = trim(mb_substr($sheetTitle, 0, 31), " '\t\n\r\0\x0B");
            $baseTitle = $sheetTitle === '' ? 'Column data' : $sheetTitle;
            $sheetTitle = $baseTitle;
            $suffix = 2;

            while (in_array(mb_strtolower($sheetTitle), $sheetNames, true)) {
                $ending = ' '.$suffix++;
                $sheetTitle = mb_substr($baseTitle, 0, 31 - mb_strlen($ending)).$ending;
            }

            $sheetNames[] = mb_strtolower($sheetTitle);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);
            $sheet->setCellValue('A1', 'Station');
            $sheet->setCellValue('B1', $group['station']);
            $sheet->setCellValue('A2', 'Parameter');
            $sheet->setCellValue('B2', $group['parameter']);
            $sheet->setCellValue('A3', 'Elevation');
            $sheet->setCellValue('B3', $group['elevation']);
            $sheet->setCellValue('A4', 'Longitude');
            $sheet->setCellValue('B4', $group['longitude']);
            $sheet->setCellValue('A5', 'Latitude');
            $sheet->setCellValue('B5', $group['latitude']);
            $sheet->setCellValue('A6', 'Period range');
            $sheet->setCellValue('B6', substr($firstMonth, 0, 4).'-'.substr($firstMonth, 5, 2).' to '.substr($lastMonth, 0, 4).'-'.substr($lastMonth, 5, 2));
            $sheet->setCellValue('C1', 'Day');
            $sheet->setCellValue('C35', 'Occurrence');
            $sheet->getStyle('A1:A6')->getFont()->setBold(true);
            $sheet->getStyle('C1:C35')->getFont()->setBold(true);

            $periodColumns = [];

            foreach ($periods as $periodIndex => $period) {
                $columnIndex = $periodIndex + 4;
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $periodKey = $period['month'].'|'.$period['occurrence'];
                $periodColumns[$periodKey] = $column;
                $year = (int) substr($period['month'], 0, 4);
                $monthNumber = (int) substr($period['month'], 5, 2);

                $sheet->setCellValue($column.'1', $year);
                $sheet->setCellValue($column.'2', $monthNumber);
                $sheet->setCellValue($column.'3', date('F', strtotime($period['month'].'-01')));
                $sheet->setCellValue($column.'35', $period['occurrence']);
            }

            for ($day = 1; $day <= 31; $day++) {
                $sheet->setCellValue('C'.($day + 3), $day);
            }

            foreach ($measurements as $record) {
                $date = $record['date'];
                $day = (int) substr($date, 8, 2);
                $column = $periodColumns[substr($date, 0, 7).'|'.$record['occurrence']];
                $cell = $column.($day + 3);

                if (($record['status'] ?? null) === 'valid') {
                    $sheet->setCellValue($cell, $record['value']);
                } elseif (($record['status'] ?? null) === 'invalid') {
                    $sheet->setCellValue($cell, 'INVALID');
                    $invalidRows[] = $record;
                }
            }

            $sheet->setCellValue('A37', 'Date');
            $sheet->setCellValue('B37', 'Value');
            $sheet->setCellValue('C37', 'Status');
            $sheet->setCellValue('D37', 'Source row');
            $sheet->getStyle('A37:D37')->getFont()->setBold(true);
            $dailyRow = 38;

            foreach ($measurements as $record) {
                $date = $record['date'];
                $sheet->setCellValue('A'.$dailyRow, $date);

                if (($record['status'] ?? null) === 'valid') {
                    $sheet->setCellValue('B'.$dailyRow, $record['value']);
                } elseif (($record['status'] ?? null) === 'invalid') {
                    $sheet->setCellValue('B'.$dailyRow, 'INVALID');
                }

                $sheet->setCellValue('C'.$dailyRow, $record['status'] ?? 'unknown');
                $sheet->setCellValue('D'.$dailyRow, $record['row_number'] ?? null);
                $dailyRow++;
            }

            $sheet->freezePane('D4');
            $sheet->getStyle('D1:'.$sheet->getHighestColumn().'3')->getFont()->setBold(true);
            $sheet->getStyle('A37:D37')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1F4');
            $sheet->getColumnDimension('A')->setWidth(18);
            $sheet->getColumnDimension('B')->setWidth(24);
            $sheet->getColumnDimension('C')->setWidth(14);
            $sheet->getColumnDimension('D')->setWidth(14);

            foreach (range(4, count($periods) + 3) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(12);
            }

            $index->fromArray([[
                $group['station'],
                $group['parameter'],
                $group['parameter_code'],
                $group['elevation'],
                $group['longitude'],
                $group['latitude'],
                $measurements[0]['date'],
                $measurements[array_key_last($measurements)]['date'],
                count($measurements),
                count($periods),
            ]], null, 'A'.$indexRow);
            $indexRow++;
        }

        if ($invalidRows !== []) {
            $issues = $spreadsheet->createSheet();
            $issues->setTitle('Review');
            $issues->fromArray([['Station', 'Date', 'Parameter', 'Status', 'Flag', 'Message', 'Source cell', 'Source row']], null, 'A1');
            $issues->getStyle('A1:H1')->getFont()->setBold(true);

            foreach ($invalidRows as $issueIndex => $record) {
                $issues->fromArray([[
                    $record['station_name'] ?? '',
                    $record['date'] ?? '',
                    $record['parameter'] ?? '',
                    $record['status'] ?? 'invalid',
                    $record['flag'] ?? '',
                    $record['message'] ?? 'The record could not be placed in the dated matrix.',
                    $record['source_cell'] ?? '',
                    $record['row_number'] ?? '',
                ]], null, 'A'.($issueIndex + 2));
            }

            foreach (range('A', 'H') as $column) {
                $issues->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $path = 'excel/'.$batch->uuid.'/exports/column-based-'.$batch->uuid.'.xlsx';
        Storage::disk('local')->makeDirectory(dirname($path));
        $absolute = Storage::disk('local')->path($path);

        (new Xlsx($spreadsheet))->save($absolute);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $absolute,
            'name' => 'column-based-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $batch->file_name),
        ];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function errorReport(DataImportBatch $batch): array
    {
        $report = $this->validation->report($batch);

        $path = 'excel/'.$batch->uuid.'/exports/validation-errors-'.$batch->uuid.'.csv';
        Storage::disk('local')->makeDirectory(dirname($path));
        $absolute = Storage::disk('local')->path($path);

        $handle = fopen($absolute, 'wb');

        if ($handle === false) {
            throw new RuntimeException('The error report could not be written.');
        }

        fputcsv($handle, ['Row', 'Station', 'Date', 'Column', 'Code', 'Severity', 'Message', 'Raw value'], ',', '"', '\\');

        foreach (($report['issues'] ?? []) as $issue) {
            fputcsv($handle, [
                $issue['row_number'] ?? '',
                $issue['station'] ?? '',
                $issue['date'] ?? '',
                $issue['column'] ?? '',
                $issue['code'] ?? '',
                $issue['severity'] ?? '',
                $issue['message'] ?? '',
                $issue['raw_value'] ?? '',
            ], ',', '"', '\\');
        }

        fclose($handle);

        return [
            'path' => $absolute,
            'name' => 'validation-errors-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $batch->file_name).'.csv',
        ];
    }

    /**
     * @return array{path: string, name: string}
     */
    public function geoJson(DataImportBatch $batch): array
    {
        $rows = DB::table('hydrological_observations as ho')
            ->join('monitoring_stations as ms', 'ms.id', '=', 'ho.monitoring_station_id')
            ->leftJoin('basins as b', 'b.id', '=', 'ms.basin_id')
            ->where('ho.data_import_batch_id', $batch->id)
            ->whereNotNull('ms.latitude')
            ->whereNotNull('ms.longitude')
            ->groupBy('ms.id', 'ms.code', 'ms.name', 'ms.latitude', 'ms.longitude', 'b.name', 'b.code')
            ->select([
                'ms.id',
                'ms.code',
                'ms.name',
                'ms.latitude',
                'ms.longitude',
                'b.name as basin_name',
                'b.code as basin_code',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(ho.value) as total_value'),
                DB::raw('AVG(ho.value) as avg_value'),
                DB::raw('MIN(ho.measured_at) as first_measured_at'),
                DB::raw('MAX(ho.measured_at) as last_measured_at'),
            ])
            ->get();

        $features = [];

        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row->longitude, (float) $row->latitude],
                ],
                'properties' => [
                    'station_id' => $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'basin' => $row->basin_name,
                    'basin_code' => $row->basin_code,
                    'records_count' => (int) $row->total_records,
                    'total_value' => round((float) $row->total_value, 2),
                    'average_value' => round((float) $row->avg_value, 2),
                    'first_observation' => $row->first_measured_at,
                    'last_observation' => $row->last_measured_at,
                    'import_batch' => $batch->uuid,
                ],
            ];
        }

        $geojson = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'batch_uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'sheet_name' => $batch->sheet_name,
                'generated_at' => now()->toIso8601String(),
            ],
        ];

        $path = 'excel/'.$batch->uuid.'/exports/geojson-'.$batch->uuid.'.geojson';
        Storage::disk('local')->makeDirectory(dirname($path));
        $absolute = Storage::disk('local')->path($path);

        file_put_contents($absolute, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'path' => $absolute,
            'name' => 'stations-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $batch->file_name).'.geojson',
        ];
    }
}
