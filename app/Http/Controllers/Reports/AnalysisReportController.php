<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AnalysisFilterRequest;
use App\Models\Basin;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Services\Reports\HydrologicalAnalysisService;
use App\Services\Reports\ReportDocumentBuilder;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hydrological analysis screen.
 *
 * One aggregation pipeline serves the page, the JSON refresh and the export, so
 * a chart and the file downloaded from it can never disagree.
 */
class AnalysisReportController extends Controller
{
    public function index(
        AnalysisFilterRequest $request,
        ReportQueryService $queries,
        HydrologicalAnalysisService $analysis,
    ): Response {
        $filters = $this->resolve($request);

        return Inertia::render('Reports/Analysis', [
            'filters' => $filters,
            'options' => [
                'parameters' => $queries->parameterOptions(),
                'basins' => Basin::query()->orderBy('sort_order')->get(['id', 'code', 'name'])
                    ->map(fn (Basin $basin) => ['id' => $basin->id, 'code' => $basin->code, 'name' => $basin->name])->all(),
                'stations' => $queries->stationOptions(),
            ],
            'result' => $this->result($filters, $analysis),
            'abilities' => $this->abilities($request),
        ]);
    }

    public function data(
        AnalysisFilterRequest $request,
        HydrologicalAnalysisService $analysis,
    ): JsonResponse {
        return response()->json($this->result($this->resolve($request), $analysis));
    }

    public function export(
        AnalysisFilterRequest $request,
        string $format,
        HydrologicalAnalysisService $analysis,
        ReportDocumentBuilder $builder,
        ReportExportService $exports,
    ): BinaryFileResponse {
        $filters = $this->resolve($request);

        abort_unless(
            $filters['quality'] !== 'all' || $request->user()->can('export restricted reports'),
            403,
            'Exporting provisional records requires the export restricted reports permission.'
        );

        $result = $this->result($filters, $analysis);
        $document = $builder->analysis($result, $filters, $result['scope']);

        $file = match ($format) {
            'csv' => $exports->csv($document),
            'xlsx' => $exports->xlsx($document),
            'pdf' => $exports->pdf($document),
            default => abort(404),
        };

        return response()->download($file['path'], $file['name'])->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function result(array $filters, HydrologicalAnalysisService $analysis): array
    {
        $parameter = Parameter::with('defaultUnit')->findOrFail($filters['parameter_id']);

        return [
            'series' => $analysis->series($filters, $filters['granularity']),
            'seasonal' => $analysis->seasonalProfile($filters),
            'stations' => $analysis->comparison($filters, 'station'),
            'basins' => $analysis->comparison($filters, 'basin'),
            'distribution' => $analysis->distribution($filters),
            'coverage' => $analysis->coverage($filters),
            'scope' => $this->scopeLabel($filters),
            'generated_at' => now()->toDateTimeString(),
            'parameter_name' => $parameter->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(AnalysisFilterRequest $request): array
    {
        $filters = $request->filters();

        if (empty($filters['parameter_id'])) {
            $filters['parameter_id'] = (int) Parameter::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->value('id');
        }

        abort_unless($filters['parameter_id'] > 0, 422, 'No parameter is available to analyse.');

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scopeLabel(array $filters): string
    {
        if (! empty($filters['station_id'])) {
            $station = MonitoringStation::find($filters['station_id']);

            return $station ? $station->name.' ('.$station->code.')' : 'Selected station';
        }

        if (! empty($filters['basin_id'])) {
            $basin = Basin::find($filters['basin_id']);

            return $basin ? $basin->name.' basin' : 'Selected basin';
        }

        return 'All stations';
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(AnalysisFilterRequest $request): array
    {
        $user = $request->user();

        return [
            'generate' => $user->can('generate reports'),
            'export' => $user->can('export reports'),
            'export_restricted' => $user->can('export restricted reports'),
        ];
    }
}
