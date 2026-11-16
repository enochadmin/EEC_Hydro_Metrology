<?php

namespace App\Services\Reports;

use App\Support\Pdf\PdfDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders one report document to the requested file format.
 *
 * Generated files live under storage/app/private/reports and are deleted by the
 * controller after the download is sent; nothing a user exports stays on disk.
 */
class ReportExportService
{
    public function __construct(private readonly PdfDocument $pdf) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array{path: string, name: string, mime: string}
     */
    public function csv(array $document): array
    {
        $path = $this->path($document, 'csv');
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create the export file.');
        }

        // BOM so Excel opens UTF-8 content without mangling non-ASCII names.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [$document['title']]);
        fputcsv($handle, ['Generated', $document['generated_at']]);
        fputcsv($handle, []);

        foreach ($document['meta'] as $item) {
            fputcsv($handle, [$item['label'], $item['value']]);
        }

        fputcsv($handle, []);

        foreach ($document['sections'] as $section) {
            fputcsv($handle, [$section['heading']]);

            $labels = array_map(fn ($column) => $column['label'], $section['columns']);
            fputcsv($handle, $labels);

            if (($section['rows'] ?? []) === []) {
                fputcsv($handle, [$section['empty'] ?? 'No records.']);
            }

            foreach ($section['rows'] as $row) {
                fputcsv($handle, array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $row));
            }

            fputcsv($handle, []);
        }

        fclose($handle);

        return ['path' => $path, 'name' => $this->filename($document, 'csv'), 'mime' => 'text/csv'];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{path: string, name: string, mime: string}
     */
    public function xlsx(array $document): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $row = 1;
        $sheet->setCellValue('A'.$row, $document['title']);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue('A'.$row, 'Generated');
        $sheet->setCellValue('B'.$row, $document['generated_at']);
        $row += 2;

        foreach ($document['meta'] as $item) {
            $sheet->setCellValue('A'.$row, $item['label']);
            $sheet->setCellValue('B'.$row, $item['value']);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $row++;

        foreach ($document['sections'] as $section) {
            $sheet->setCellValue('A'.$row, $section['heading']);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(11);
            $row++;

            $columnCount = count($section['columns']);

            foreach ($section['columns'] as $index => $column) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$row, $column['label']);
            }

            $headerRow = $row;
            $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
            $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('EDF1F5');
            $row++;

            if (($section['rows'] ?? []) === []) {
                $sheet->setCellValue('A'.$row, $section['empty'] ?? 'No records.');
                $row += 2;

                continue;
            }

            foreach ($section['rows'] as $dataRow) {
                foreach ($dataRow as $index => $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$row, $value);
                }

                $row++;
            }

            $row++;
            unset($headerRow);
        }

        foreach (range(1, max(1, count($document['sections'][0]['columns'] ?? [1, 2]))) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $path = $this->path($document, 'xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $path,
            'name' => $this->filename($document, 'xlsx'),
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{path: string, name: string, mime: string}
     */
    public function pdf(array $document): array
    {
        $path = $this->path($document, 'pdf');

        File::put($path, $this->pdf->render($document));

        return ['path' => $path, 'name' => $this->filename($document, 'pdf'), 'mime' => 'application/pdf'];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array{path: string, name: string, mime: string}
     */
    public function geoJson(array $features, string $name): array
    {
        $payload = [
            'type' => 'FeatureCollection',
            'name' => $name,
            'crs' => [
                'type' => 'name',
                'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84'],
            ],
            'features' => array_values($features),
        ];

        $filename = Str::slug($name).'-'.now()->format('Ymd-His').'.geojson';
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['path' => $path, 'name' => $filename, 'mime' => 'application/geo+json'];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function path(array $document, string $extension): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$this->filename($document, $extension);
    }

    private function directory(): string
    {
        $directory = storage_path(config('reports.export_path', 'app/private/reports'));

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function filename(array $document, string $extension): string
    {
        return Str::slug($document['title']).'-'.now()->format('Ymd-His').'.'.$extension;
    }
}
