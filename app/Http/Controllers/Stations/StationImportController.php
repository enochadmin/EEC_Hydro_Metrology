<?php

namespace App\Http\Controllers\Stations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stations\ImportStationsRequest;
use App\Http\Requests\Stations\PreviewStationImportRequest;
use App\Models\Basin;
use App\Models\DataSource;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\StationImportBatch;
use App\Models\SubBasin;
use App\Services\Stations\StationRegistryImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Station registry import: upload → preview → commit.
 *
 * The workbook describes the network, not readings, so it uses its own batch
 * table and never touches the observation pipeline. Nothing is written to
 * `monitoring_stations` until the operator confirms the staged preview.
 */
class StationImportController extends Controller
{
    public function __construct(private readonly StationRegistryImporter $importer) {}

    public function create(Request $request): Response
    {
        Gate::authorize('create', MonitoringStation::class);

        return Inertia::render('Stations/Import', [
            'defaults' => $this->importer->defaults(),
            'limits' => [
                'max_kb' => (int) config('stations.import.max_upload_kb'),
                'formats' => ['xlsx', 'xls', 'csv'],
                'max_rows' => (int) config('stations.import.max_rows'),
            ],
            'classes' => $this->classOptions(),
            'basins' => $this->basinOptions(),
            'subBasins' => $this->subBasinOptions(),
            'parameters' => $this->parameterOptions(),
            'dataSources' => $this->dataSourceOptions(),
            'recent' => $this->recentBatches(),
        ]);
    }

    public function store(ImportStationsRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $directory = 'station-imports/'.$uuid;
        $path = $file->storeAs($directory, 'source.'.$extension, 'local');

        $batch = StationImportBatch::create([
            'uuid' => $uuid,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id,
            'status' => 'pending',
            'stage' => 'uploaded',
        ]);

        try {
            $summary = $this->importer->preview($batch, $request->options());
        } catch (Throwable $exception) {
            $batch->forceFill(['status' => 'failed', 'stage' => 'failed'])->save();

            return back()->with('error', 'The station list could not be read: '.$exception->getMessage());
        }

        if ($summary['ready'] === 0) {
            return redirect()
                ->route('stations.import.show', $batch->uuid)
                ->with('warning', 'No row passed validation. Review the findings below before importing.');
        }

        return redirect()
            ->route('stations.import.show', $batch->uuid)
            ->with('success', sprintf(
                '%d stations ready to import: %d duplicates, %d errors, %d warnings.',
                $summary['ready'],
                $summary['duplicates'],
                $summary['errors'],
                $summary['warnings'],
            ));
    }

    public function show(Request $request, StationImportBatch $batch): Response
    {
        Gate::authorize('create', MonitoringStation::class);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $staged = $this->importer->stagedRows($batch, $perPage, ($page - 1) * $perPage);

        return Inertia::render('Stations/ImportPreview', [
            'batch' => [
                'uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'file_size' => (int) $batch->file_size,
                'status' => $batch->status,
                'stage' => $batch->stage,
                'summary' => $batch->summary,
                'options' => $batch->options,
                'mapping' => $batch->mapping,
                'total_rows' => (int) $batch->total_rows,
                'importable_rows' => (int) $batch->importable_rows,
                'imported_rows' => (int) $batch->imported_rows,
                'duplicate_rows' => (int) $batch->duplicate_rows,
                'error_rows' => (int) $batch->error_rows,
                'imported_at' => $batch->imported_at?->toIso8601String(),
                'created_at' => $batch->created_at?->toIso8601String(),
            ],
            'rows' => [
                'data' => $staged['rows'],
                'total' => $staged['total'],
                'page' => $page,
                'per_page' => $perPage,
            ],
            'issues' => $batch->errors()
                ->orderByRaw("CASE severity WHEN 'error' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->orderBy('row_number')
                ->limit(120)
                ->get(['row_number', 'column_name', 'code', 'severity', 'message'])
                ->all(),
            'classes' => $this->classOptions(),
            'basins' => $this->basinOptions(),
            'subBasins' => $this->subBasinOptions(),
            'parameters' => $this->parameterOptions(),
            'dataSources' => $this->dataSourceOptions(),
            'defaults' => $this->importer->defaults(),
            'abilities' => [
                'commit' => Gate::allows('create', MonitoringStation::class),
            ],
        ]);
    }

    public function preview(PreviewStationImportRequest $request, StationImportBatch $batch): RedirectResponse
    {
        try {
            $summary = $this->importer->preview($batch, $request->options());
        } catch (Throwable $exception) {
            return back()->with('error', 'The preview could not be rebuilt: '.$exception->getMessage());
        }

        return redirect()
            ->route('stations.import.show', $batch->uuid)
            ->with('success', sprintf(
                'Preview rebuilt: %d ready, %d duplicates, %d errors.',
                $summary['ready'],
                $summary['duplicates'],
                $summary['errors'],
            ));
    }

    public function commit(PreviewStationImportRequest $request, StationImportBatch $batch): RedirectResponse
    {
        if ($batch->status === 'imported') {
            return redirect()
                ->route('stations.import.show', $batch->uuid)
                ->with('warning', 'This station list was already imported. Upload a new file to add more stations.');
        }

        $result = $this->importer->commit($batch, $request->options(), $request->user());

        if ($result['imported'] === 0) {
            return redirect()
                ->route('stations.import.show', $batch->uuid)
                ->with('warning', 'No station was imported: every row was a duplicate or failed validation.');
        }

        return redirect()
            ->route('stations.index')
            ->with('success', sprintf(
                '%d stations imported. %d duplicates skipped, %d rows rejected.',
                $result['imported'],
                $result['duplicates'],
                $result['errors'],
            ));
    }

    /**
     * @return list<array{code: string, label: string, color: string, description: string}>
     */
    private function classOptions(): array
    {
        $options = [];

        foreach (config('stations.classes') as $code => $definition) {
            $options[] = ['code' => $code, ...$definition];
        }

        return $options;
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function basinOptions(): array
    {
        return Basin::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Basin $basin): array => ['id' => $basin->id, 'code' => $basin->code, 'name' => $basin->name])
            ->all();
    }

    /**
     * @return list<array{id: int, basin_id: int, code: string, name: string}>
     */
    private function subBasinOptions(): array
    {
        return SubBasin::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'basin_id', 'code', 'name'])
            ->map(fn (SubBasin $subBasin): array => [
                'id' => $subBasin->id,
                'basin_id' => $subBasin->basin_id,
                'code' => $subBasin->code,
                'name' => $subBasin->name,
            ])
            ->all();
    }

    /**
     * @return list<array{code: string, name: string, symbol: string|null}>
     */
    private function parameterOptions(): array
    {
        return Parameter::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name', 'symbol'])
            ->map(fn (Parameter $parameter): array => [
                'code' => $parameter->code,
                'name' => $parameter->name,
                'symbol' => $parameter->symbol,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, organisation: string|null}>
     */
    private function dataSourceOptions(): array
    {
        return DataSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'organisation'])
            ->map(fn (DataSource $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'organisation' => $source->organisation,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentBatches(): array
    {
        return StationImportBatch::query()
            ->with('uploader:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (StationImportBatch $batch): array => [
                'uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'status' => $batch->status,
                'total_rows' => (int) $batch->total_rows,
                'imported_rows' => (int) $batch->imported_rows,
                'uploaded_by' => $batch->uploader?->name,
                'created_at' => $batch->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
