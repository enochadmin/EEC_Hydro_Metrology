<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Basin;
use App\Services\Reports\HydrologicalAnalysisService;
use App\Services\Reports\ReportDocumentBuilder;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Basin report: specifications, geography, sub-basins, stations, parameters and
 * coverage, plus the same document as CSV, Excel, PDF or GeoJSON.
 */
class BasinReportController extends Controller
{
    public function show(
        Request $request,
        Basin $basin,
        ReportQueryService $queries,
        HydrologicalAnalysisService $analysis,
    ): Response {
        $report = $queries->basin($basin);

        $trend = null;

        if ($report['parameters'] !== []) {
            $parameter = $report['parameters'][0];

            $trend = $analysis->series([
                'parameter_id' => $parameter['parameter_id'],
                'basin_id' => $basin->id,
            ], 'monthly');
        }

        return Inertia::render('Reports/Basin', [
            'report' => $report,
            'trend' => $trend,
            'abilities' => $this->abilities($request),
        ]);
    }

    public function export(
        Request $request,
        Basin $basin,
        string $format,
        ReportQueryService $queries,
        ReportDocumentBuilder $builder,
        ReportExportService $exports,
    ): BinaryFileResponse {
        if ($format === 'geojson') {
            $file = $exports->geoJson(
                $queries->basinFeatures($basin),
                'basin-'.$basin->code.'-geography'
            );

            return response()->download($file['path'], $file['name'])->deleteFileAfterSend(true);
        }

        $report = $queries->basin($basin);

        $this->guardProvisional($request, $report['quality']);

        $document = $builder->basin($report);

        $file = match ($format) {
            'csv' => $exports->csv($document),
            'xlsx' => $exports->xlsx($document),
            'pdf' => $exports->pdf($document),
            default => abort(404),
        };

        return response()->download($file['path'], $file['name'])->deleteFileAfterSend(true);
    }

    /**
     * A basin export that would include provisional observations is restricted.
     *
     * @param  array<string, mixed>  $quality
     */
    private function guardProvisional(Request $request, array $quality): void
    {
        $provisional = $quality['pending'] + $quality['flagged'] + $quality['rejected'];

        abort_unless(
            $provisional === 0 || $request->user()->can('export restricted reports'),
            403,
            'This report contains provisional observations. Exporting it requires the export restricted reports permission.'
        );
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'export' => $user->can('export reports'),
            'export_restricted' => $user->can('export restricted reports'),
        ];
    }
}
