<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Models\Basin;
use App\Models\DataImportBatch;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\SubBasin;
use App\Services\Excel\ExcelValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExcelValidationController extends Controller
{
    public function show(Request $request, DataImportBatch $batch, ExcelValidationService $validation): Response
    {
        $report = $validation->report($batch);

        $stations = collect($report['stations'] ?? [])->map(function (array $station): array {
            $match = MonitoringStation::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $station['name'])])
                ->first();

            return [
                ...$station,
                'suggested_station_id' => $match?->id,
                'suggested_station_code' => $match?->code,
            ];
        });

        return Inertia::render('Excel/Validation', [
            'batch' => [
                'uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'sheet_name' => $batch->sheet_name,
                'structure_type' => $batch->structure_type,
                'summary' => $batch->summary,
                'validation_summary' => $batch->validation_summary,
                'station_map' => $batch->station_map ?? [],
                'station' => $batch->station?->only(['id', 'code', 'name', 'name_alt']),
                'stage' => $batch->stage,
                'status' => $batch->status,
                'imported_rows' => $batch->imported_rows,
            ],
            'report' => [
                'summary' => $report['summary'] ?? [],
                'issues' => array_slice($report['issues'] ?? [], 0, 250),
                'issues_total' => count($report['issues'] ?? []),
            ],
            'stations' => $stations,
            'parameters' => Parameter::query()->with('defaultUnit:id,symbol')->orderBy('name')->get(['id', 'code', 'name', 'default_unit_id'])
                ->map(fn (Parameter $parameter): array => [
                    'id' => $parameter->id,
                    'code' => $parameter->code,
                    'name' => $parameter->name,
                    'unit' => $parameter->defaultUnit?->symbol,
                ]),
            'existingStations' => MonitoringStation::query()
                ->with(['basin:id,code,name', 'subBasin:id,code,name'])
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'basin_id', 'sub_basin_id'])
                ->map(fn (MonitoringStation $station): array => [
                    'id' => $station->id,
                    'code' => $station->code,
                    'name' => $station->name,
                    'basin' => $station->basin?->only(['id', 'code', 'name']),
                    'sub_basin' => $station->subBasin?->only(['id', 'code', 'name']),
                ]),
            'basins' => Basin::query()->orderBy('name')->get(['id', 'code', 'name']),
            'subBasins' => SubBasin::query()->orderBy('name')->get(['id', 'basin_id', 'code', 'name']),
        ]);
    }

    public function validate(DataImportBatch $batch, ExcelValidationService $validation): RedirectResponse
    {
        try {
            $result = $validation->validate($batch);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('excel.validation', $batch)
            ->with('success', sprintf(
                'Validation finished: %d valid, %d missing, %d invalid rows.',
                $result['summary']['valid_rows'],
                $result['summary']['missing_rows'],
                $result['summary']['invalid_rows'],
            ));
    }
}
