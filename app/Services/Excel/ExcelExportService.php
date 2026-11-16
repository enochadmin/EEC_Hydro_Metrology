<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
