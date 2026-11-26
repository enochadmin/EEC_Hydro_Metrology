<?php

namespace App\Http\Controllers\Stations;

use App\Http\Controllers\Controller;
use App\Models\DataImportBatch;
use App\Models\MonitoringStation;
use App\Services\Stations\StationDataComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Row-based versus column-based comparison for one station.
 *
 * Both uploads are listed from the station's own import history, so the
 * comparison cannot reach another station's data even by guessing a batch id.
 */
class StationDataComparisonController extends Controller
{
    public function show(Request $request, MonitoringStation $station, StationDataComparisonService $comparisons): Response
    {
        Gate::authorize('view', $station);

        $batches = $comparisons->batchesFor($station);

        $options = $batches->map(fn (DataImportBatch $batch): array => [
            'uuid' => $batch->uuid,
            'file_name' => $batch->file_name,
            'sheet_name' => $batch->sheet_name,
            'structure_type' => $batch->structure_type,
            'stage' => $batch->stage,
            'imported_at' => $batch->imported_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ])->all();

        $left = $this->resolve($batches, $request->query('left'));
        $right = $this->resolve($batches, $request->query('right'));

        $comparison = null;
        $error = null;

        if ($left !== null && $right !== null) {
            if ($left->is($right)) {
                $error = 'Choose two different uploads.';
            } else {
                try {
                    $comparison = $comparisons->compare($left, $right);
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                }
            }
        }

        return Inertia::render('Stations/Compare', [
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'station_class' => $station->station_class,
                'class_label' => $station->classLabel(),
            ],
            'batches' => $options,
            'selected' => [
                'left' => $left?->uuid,
                'right' => $right?->uuid,
            ],
            'comparison' => $comparison,
            'error' => $error,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DataImportBatch>  $batches
     */
    private function resolve($batches, mixed $uuid): ?DataImportBatch
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return $batches->firstWhere('uuid', $uuid);
    }
}
