<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Excel\ImportExcelRequest;
use App\Http\Requests\Excel\StoreExcelUploadRequest;
use App\Models\DataImportBatch;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Services\Excel\ExcelFileService;
use App\Services\Excel\ExcelImportBatchDeletionService;
use App\Services\Excel\ExcelImportService;
use App\Services\Excel\ExcelStationImportPreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExcelImportController extends Controller
{
    public function __construct(private readonly ExcelFileService $files) {}

    public function index(): Response
    {
        $batches = DataImportBatch::query()
            ->with('uploader:id,name')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (DataImportBatch $batch): array => $this->batchRow($batch));

        return Inertia::render('Excel/Index', [
            'stats' => [
                'uploads' => DataImportBatch::query()->count(),
                'imported_batches' => DataImportBatch::query()->where('stage', 'imported')->count(),
                'observations' => (int) DataImportBatch::query()->sum('imported_rows'),
                'rejected' => (int) DataImportBatch::query()->sum('rejected_rows'),
            ],
            'recent' => $batches,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Excel/Upload', [
            'limits' => [
                'max_kb' => (int) config('excel.limits.max_upload_kb'),
                'formats' => ['xlsx', 'xls', 'csv'],
            ],
        ]);
    }

    public function createForStation(MonitoringStation $station): Response
    {
        Gate::authorize('view', $station);

        return Inertia::render('Excel/Upload', [
            'limits' => [
                'max_kb' => (int) config('excel.limits.max_upload_kb'),
                'formats' => ['xlsx', 'xls', 'csv'],
            ],
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'upload_url' => route('stations.imports.store', $station),
                'configured_parameters' => $station->stationParameters()
                    ->where('is_active', true)
                    ->with('parameter:id,code,name')
                    ->get()
                    ->pluck('parameter.code')
                    ->filter()
                    ->values()
                    ->all(),
                'parameters' => Parameter::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['code', 'name', 'symbol'])
                    ->map(fn (Parameter $parameter): array => [
                        'code' => $parameter->code,
                        'name' => $parameter->name,
                        'symbol' => $parameter->symbol,
                    ])
                    ->all(),
            ],
        ]);
    }

    public function store(StoreExcelUploadRequest $request): RedirectResponse
    {
        return $this->storeWorkbook($request);
    }

    public function storeForStation(
        StoreExcelUploadRequest $request,
        MonitoringStation $station,
        ExcelStationImportPreparationService $preparation,
    ): RedirectResponse {
        Gate::authorize('view', $station);

        return $this->storeWorkbook($request, $station, $preparation, $request->validated('parameter_code'));
    }

    private function storeWorkbook(
        StoreExcelUploadRequest $request,
        ?MonitoringStation $station = null,
        ?ExcelStationImportPreparationService $preparation = null,
        ?string $parameterCode = null,
    ): RedirectResponse {
        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $directory = 'excel/'.$uuid;

        $path = $file->storeAs($directory, 'source.'.$extension, 'local');

        $batch = DataImportBatch::create([
            'uuid' => $uuid,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize() ?: 0,
            'mime_type' => $file->getClientMimeType(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'monitoring_station_id' => $station?->id,
            'data_source_id' => $station?->data_source_id,
            'uploaded_by' => $request->user()->id,
            'status' => 'pending',
            'stage' => 'uploaded',
            'notes' => 'Original workbook preserved on the private disk.',
        ]);

        try {
            $sheets = $this->files->sheetNames($batch);
        } catch (\Throwable $exception) {
            $batch->forceFill(['status' => 'failed', 'notes' => $exception->getMessage()])->save();

            return back()->with('error', 'The workbook could not be read: '.$exception->getMessage());
        }

        if ($station !== null && $preparation !== null) {
            try {
                $prepared = $preparation->prepare($batch, $parameterCode);
            } catch (\Throwable $exception) {
                return redirect()
                    ->route('excel.preview', ['batch' => $batch->uuid, 'sheet' => $sheets[0] ?? null])
                    ->with('error', 'The workbook was saved, but automatic transformation could not complete. Review the worksheet and mapping before continuing.');
            }

            if ($prepared) {
                return redirect()
                    ->route('excel.validation', $batch)
                    ->with('success', 'Workbook uploaded, transformed and validated for '.$station->code.'. Review the findings before approving the import.');
            }

            return redirect()
                ->route('excel.preview', ['batch' => $batch->uuid, 'sheet' => $sheets[0] ?? null])
                ->with('warning', 'The workbook was saved, but its structure needs manual mapping before it can be imported.');
        }

        return redirect()
            ->route('excel.preview', ['batch' => $batch->uuid, 'sheet' => $sheets[0] ?? null])
            ->with('success', 'Workbook uploaded. '.count($sheets).' worksheet(s) detected.');
    }

    public function import(ImportExcelRequest $request, DataImportBatch $batch, ExcelImportService $importer): RedirectResponse
    {
        $stationMap = $request->validated('station_map');

        $counts = $importer->import($batch, $stationMap, $request->user());

        $destination = $batch->monitoring_station_id
            ? redirect()->route('stations.show', ['station' => $batch->monitoring_station_id, 'tab' => 'imports'])
            : redirect()->route('excel.validation', $batch);

        return $destination
            ->with('success', sprintf(
                '%d observations imported; %d duplicates skipped; %d non-valid rows excluded.',
                $counts['imported'],
                $counts['duplicates'],
                $counts['skipped'],
            ));
    }

    public function history(): Response
    {
        $batches = DataImportBatch::query()
            ->with('uploader:id,name')
            ->latest()
            ->paginate(15)
            ->through(fn (DataImportBatch $batch): array => $this->batchRow($batch));

        return Inertia::render('Excel/ImportHistory', ['batches' => $batches]);
    }

    public function destroy(Request $request, DataImportBatch $batch, ExcelImportBatchDeletionService $deletion): RedirectResponse
    {
        abort_unless($request->user()?->can('delete data'), 403);

        $fileName = $batch->file_name;
        $result = $deletion->delete($batch);
        $message = 'Workbook "'.$fileName.'" and '.$result['observations_deleted'].' imported observation(s) deleted.';

        if (! $result['files_removed']) {
            return back()->with('warning', $message.' The private workbook files could not be fully removed; check the application storage logs.');
        }

        return back()->with('success', $message.' Station records were retained.');
    }

    /**
     * @return array<string, mixed>
     */
    private function batchRow(DataImportBatch $batch): array
    {
        return [
            'uuid' => $batch->uuid,
            'file_name' => $batch->file_name,
            'file_size' => $batch->file_size,
            'sheet_name' => $batch->sheet_name,
            'structure_type' => $batch->structure_type,
            'uploaded_by' => $batch->uploader?->name,
            'created_at' => $batch->created_at?->toIso8601String(),
            'imported_at' => $batch->imported_at?->toIso8601String(),
            'status' => $batch->status,
            'stage' => $batch->stage,
            'total_rows' => (int) $batch->total_rows,
            'imported_rows' => (int) $batch->imported_rows,
            'duplicate_rows' => (int) $batch->duplicate_rows,
            'rejected_rows' => (int) $batch->rejected_rows,
            'summary' => $batch->summary,
        ];
    }
}
