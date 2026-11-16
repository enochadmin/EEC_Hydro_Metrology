<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Models\DataImportBatch;
use App\Services\Excel\ExcelExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportController extends Controller
{
    public function transformed(DataImportBatch $batch, ExcelExportService $exports): BinaryFileResponse
    {
        $file = $exports->transformedWorkbook($batch);

        return response()
            ->download($file['path'], $file['name'])
            ->deleteFileAfterSend(true);
    }

    public function errors(DataImportBatch $batch, ExcelExportService $exports): BinaryFileResponse
    {
        $file = $exports->errorReport($batch);

        return response()
            ->download($file['path'], $file['name'])
            ->deleteFileAfterSend(true);
    }

    public function geojson(DataImportBatch $batch, ExcelExportService $exports): BinaryFileResponse
    {
        $file = $exports->geoJson($batch);

        return response()
            ->download($file['path'], $file['name'], ['Content-Type' => 'application/geo+json'])
            ->deleteFileAfterSend(true);
    }
}
