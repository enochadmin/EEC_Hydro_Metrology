<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\MonitoringStation;
use App\Services\Reports\HydrologicalAnalysisService;
use App\Services\Reports\ReportDocumentBuilder;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Station report: identification, available parameters, coverage, missing data,
 * quality indicators and recent observations.
 */
class StationReportController extends Controller
{
    public function show(
        Request $request,
        MonitoringStation $station,
        ReportQueryService $queries,
        HydrologicalAnalysisService $analysis,
    ): Response {
        $report = $queries->station($station);

        $trend = null;

        if ($report['parameters'] !== []) {
            $parameter = $report['parameters'][0];

            $trend = $analysis->series([
                'parameter_id' => $parameter['parameter_id'],
                'station_id' => $station->id,
            ], 'monthly');
        }

        return Inertia::render('Reports/Station', [
            'report' => $report,
            'trend' => $trend,
            'abilities' => [
                'export' => $request->user()->can('export reports'),
                'export_restricted' => $request->user()->can('export restricted reports'),
            ],
        ]);
    }

    public function export(
        Request $request,
        MonitoringStation $station,
        string $format,
        ReportQueryService $queries,
        ReportDocumentBuilder $builder,
        ReportExportService $exports,
    ): BinaryFileResponse {
        if ($format === 'geojson') {
            $file = $exports->geoJson(
                $queries->stationFeatures($station),
                'station-'.$station->code.'-location'
            );

            return response()->download($file['path'], $file['name'])->deleteFileAfterSend(true);
        }

        $report = $queries->station($station);
        $quality = $report['quality'];
        $provisional = $quality['pending'] + $quality['flagged'] + $quality['rejected'];

        abort_unless(
            $provisional === 0 || $request->user()->can('export restricted reports'),
            403,
            'This report contains provisional observations. Exporting it requires the export restricted reports permission.'
        );

        $document = $builder->station($report);

        $file = match ($format) {
            'csv' => $exports->csv($document),
            'xlsx' => $exports->xlsx($document),
            'pdf' => $exports->pdf($document),
            default => abort(404),
        };

        return response()->download($file['path'], $file['name'])->deleteFileAfterSend(true);
    }
}
