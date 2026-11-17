<?php

namespace App\Http\Controllers\Stations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stations\StoreMonitoringStationRequest;
use App\Http\Requests\Stations\StoreStationDocumentRequest;
use App\Http\Requests\Stations\UpdateMonitoringStationRequest;
use App\Models\ActivityLog;
use App\Models\Basin;
use App\Models\DataQualityIssue;
use App\Models\DataSource;
use App\Models\Document;
use App\Models\HydrologicalObservation;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\StationCategory;
use App\Models\SubBasin;
use App\Services\Documents\DocumentService;
use App\Services\Stations\MonitoringStationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringStationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MonitoringStation::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'station_type' => ['nullable', 'string', 'max:40'],
            'basin_id' => ['nullable', 'integer'],
            'sub_basin_id' => ['nullable', 'integer'],
            'state' => ['nullable', 'in:active,inactive'],
            'availability' => ['nullable', 'in:with_data,without_data'],
            'sort' => ['nullable', 'in:code,name,station_type,observations_count,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';
        $stations = $this->filteredStations($filters)
            ->with([
                'basin:id,code,name',
                'subBasin:id,code,name',
                'category:code,label,colour',
                'stationParameters.parameter:id,code,name',
                'stationParameters.unit:id,code,symbol',
            ])
            ->withCount('observations')
            ->withMin('observations', 'measured_at')
            ->withMax('observations', 'measured_at')
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MonitoringStation $station): array => $this->stationListPayload($station));

        return Inertia::render('Stations/Index', [
            'stations' => $stations,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'station_type' => $filters['station_type'] ?? '',
                'basin_id' => $filters['basin_id'] ?? '',
                'sub_basin_id' => $filters['sub_basin_id'] ?? '',
                'state' => $filters['state'] ?? '',
                'availability' => $filters['availability'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'categories' => $this->categoryOptions(),
            'basins' => $this->basinOptions(),
            'subBasins' => $this->subBasinOptions(),
            'summary' => [
                'total' => MonitoringStation::query()->count(),
                'active' => MonitoringStation::query()->where('is_active', true)->count(),
                'mapped' => MonitoringStation::query()->whereNotNull('latitude')->whereNotNull('longitude')->count(),
                'with_data' => MonitoringStation::query()->has('observations')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', MonitoringStation::class);

        $defaultBasinId = $request->integer('basin_id') ?: null;

        return Inertia::render('Stations/Create', [
            'categories' => $this->categoryOptions(),
            'basins' => $this->basinOptions(),
            'subBasins' => $this->subBasinOptions(),
            'parameters' => $this->parameterOptions(),
            'dataSources' => $this->dataSourceOptions(),
            'defaultBasinId' => $defaultBasinId,
        ]);
    }

    public function store(StoreMonitoringStationRequest $request, MonitoringStationService $stations): RedirectResponse
    {
        $station = $stations->create($request->validated(), $request->user());

        return redirect()
            ->route('stations.show', $station)
            ->with('success', 'Station '.$station->code.' registered.');
    }

    public function show(MonitoringStation $station): Response
    {
        Gate::authorize('view', $station);

        $station->load([
            'basin:id,code,name',
            'subBasin:id,code,name',
            'watershed:id,code,name',
            'category:code,label,colour,description',
            'dataSource:id,name,organisation',
            'stationParameters.parameter:id,code,name,symbol,description',
            'stationParameters.unit:id,code,name,symbol',
        ]);

        $observations = $station->observations()
            ->with(['parameter:id,code,name,symbol', 'unit:id,code,symbol', 'dataSource:id,name'])
            ->withCount('qualityIssues')
            ->orderByDesc('measured_at')
            ->paginate(15, ['*'], 'observation_page')
            ->withQueryString()
            ->through(fn (HydrologicalObservation $observation): array => [
                'id' => $observation->id,
                'parameter' => $observation->parameter?->only(['id', 'code', 'name', 'symbol']),
                'unit' => $observation->unit?->symbol,
                'measured_at' => $observation->measured_at?->toIso8601String(),
                'value' => $observation->value,
                'quality_status' => $observation->quality_status,
                'quality_issues_count' => (int) $observation->quality_issues_count,
                'data_source' => $observation->dataSource?->name,
            ]);

        $availability = $station->observations()
            ->selectRaw('COUNT(*) as total, MIN(measured_at) as first_at, MAX(measured_at) as last_at')
            ->first();

        $documents = $station->documents()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(fn (Document $document): array => $this->documentPayload($document));

        $qualityIssues = $station->qualityIssues()
            ->with(['parameter:id,code,name', 'resolvedBy:id,name'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'resolved' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (DataQualityIssue $issue): array => [
                'id' => $issue->id,
                'issue_type' => $issue->issue_type,
                'severity' => $issue->severity,
                'description' => $issue->description,
                'status' => $issue->status,
                'parameter' => $issue->parameter?->name,
                'resolved_by' => $issue->resolvedBy?->name,
                'resolved_at' => $issue->resolved_at?->toIso8601String(),
                'created_at' => $issue->created_at?->toIso8601String(),
            ]);

        $activity = $station->activityLogs()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Stations/Show', [
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'name_alt' => $station->name_alt,
                'station_type' => $station->station_type,
                'category' => $station->category?->only(['code', 'label', 'colour', 'description']),
                'basin' => $station->basin?->only(['id', 'code', 'name']),
                'sub_basin' => $station->subBasin?->only(['id', 'code', 'name']),
                'watershed' => $station->watershed?->only(['id', 'code', 'name']),
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'elevation_m' => $station->elevation_m,
                'operator' => $station->operator,
                'established_on' => $station->established_on?->toDateString(),
                'data_status' => $station->data_status,
                'is_active' => $station->is_active,
                'notes' => $station->notes,
                'data_source' => $station->dataSource?->only(['id', 'name', 'organisation']),
                'parameters' => $station->stationParameters->map(fn ($stationParameter): array => [
                    'id' => $stationParameter->parameter?->id,
                    'code' => $stationParameter->parameter?->code,
                    'name' => $stationParameter->parameter?->name,
                    'symbol' => $stationParameter->parameter?->symbol,
                    'unit' => $stationParameter->unit?->symbol ?? $stationParameter->unit?->code,
                    'sensor_reference' => $stationParameter->sensor_reference,
                    'depth_m' => $stationParameter->depth_m,
                    'starts_on' => $stationParameter->starts_on?->toDateString(),
                    'ends_on' => $stationParameter->ends_on?->toDateString(),
                    'is_active' => $stationParameter->is_active,
                ])->values(),
                'created_at' => $station->created_at?->toIso8601String(),
                'updated_at' => $station->updated_at?->toIso8601String(),
            ],
            'observations' => $observations,
            'documents' => $documents,
            'qualityIssues' => $qualityIssues,
            'activity' => $activity,
            'availability' => [
                'records' => (int) ($availability->total ?? 0),
                'first_at' => $availability?->first_at,
                'last_at' => $availability?->last_at,
                'parameters' => $station->stationParameters()->count(),
                'open_issues' => $station->qualityIssues()->where('status', 'open')->count(),
            ],
            'abilities' => [
                'update' => Gate::allows('update', $station),
                'delete' => Gate::allows('delete', $station),
                'manageDocuments' => Gate::allows('manageDocuments', $station),
            ],
        ]);
    }

    public function edit(MonitoringStation $station): Response
    {
        Gate::authorize('update', $station);

        return Inertia::render('Stations/Edit', [
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'name_alt' => $station->name_alt,
                'station_type' => $station->station_type,
                'basin_id' => $station->basin_id,
                'sub_basin_id' => $station->sub_basin_id,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'elevation_m' => $station->elevation_m,
                'established_on' => $station->established_on?->toDateString(),
                'operator' => $station->operator,
                'data_source_id' => $station->data_source_id,
                'data_status' => $station->data_status,
                'is_active' => $station->is_active,
                'notes' => $station->notes,
                'parameter_ids' => $station->parameters()->pluck('parameters.id')->map(fn ($id): int => (int) $id)->all(),
            ],
            'categories' => $this->categoryOptions($station->station_type),
            'basins' => $this->basinOptions((int) $station->basin_id),
            'subBasins' => $this->subBasinOptions($station->sub_basin_id ? (int) $station->sub_basin_id : null),
            'parameters' => $this->parameterOptions($station->parameters()->pluck('parameters.id')->map(fn ($id): int => (int) $id)->all()),
            'dataSources' => $this->dataSourceOptions($station->data_source_id ? (int) $station->data_source_id : null),
        ]);
    }

    public function update(UpdateMonitoringStationRequest $request, MonitoringStation $station, MonitoringStationService $stations): RedirectResponse
    {
        $stations->update($station, $request->validated(), $request->user());

        return redirect()
            ->route('stations.show', $station)
            ->with('success', 'Station '.$station->code.' updated.');
    }

    public function destroy(Request $request, MonitoringStation $station, MonitoringStationService $stations): RedirectResponse
    {
        Gate::authorize('delete', $station);
        $code = $station->code;
        $stations->delete($station);

        return redirect()
            ->route('stations.index')
            ->with('success', 'Station '.$code.' archived. Its observation history is retained.');
    }

    public function storeDocument(StoreStationDocumentRequest $request, MonitoringStation $station, DocumentService $documents): RedirectResponse
    {
        $document = $documents->store($station, $request->file('file'), $request->validated('title'), $request->user());

        return back()->with('success', 'Document "'.$document->title.'" uploaded.');
    }

    public function downloadDocument(MonitoringStation $station, Document $document): StreamedResponse
    {
        Gate::authorize('view', $station);
        $this->assertDocumentBelongsTo($station, $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'The stored file is missing.');

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(Request $request, MonitoringStation $station, Document $document, DocumentService $documents): RedirectResponse
    {
        Gate::authorize('manageDocuments', $station);
        $this->assertDocumentBelongsTo($station, $document);
        $documents->delete($document, $request->user());

        return back()->with('success', 'Document removed.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<MonitoringStation>
     */
    private function filteredStations(array $filters): Builder
    {
        return MonitoringStation::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('code', 'like', '%'.$term.'%')
                        ->orWhere('name', 'like', '%'.$term.'%')
                        ->orWhere('name_alt', 'like', '%'.$term.'%')
                        ->orWhere('operator', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['station_type'] ?? null, fn (Builder $query, string $type) => $query->where('station_type', $type))
            ->when($filters['basin_id'] ?? null, fn (Builder $query, int $basinId) => $query->where('basin_id', $basinId))
            ->when($filters['sub_basin_id'] ?? null, fn (Builder $query, int $subBasinId) => $query->where('sub_basin_id', $subBasinId))
            ->when(($filters['state'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['state'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when(($filters['availability'] ?? null) === 'with_data', fn (Builder $query) => $query->has('observations'))
            ->when(($filters['availability'] ?? null) === 'without_data', fn (Builder $query) => $query->doesntHave('observations'));
    }

    /**
     * @return array<string, mixed>
     */
    private function stationListPayload(MonitoringStation $station): array
    {
        return [
            'id' => $station->id,
            'code' => $station->code,
            'name' => $station->name,
            'name_alt' => $station->name_alt,
            'station_type' => $station->station_type,
            'category' => $station->category?->only(['code', 'label', 'colour']),
            'basin' => $station->basin?->only(['id', 'code', 'name']),
            'sub_basin' => $station->subBasin?->only(['id', 'code', 'name']),
            'parameters' => $station->stationParameters->map(fn ($stationParameter): array => [
                'id' => $stationParameter->parameter?->id,
                'code' => $stationParameter->parameter?->code,
                'name' => $stationParameter->parameter?->name,
                'unit' => $stationParameter->unit?->symbol ?? $stationParameter->unit?->code,
            ])->values(),
            'is_active' => $station->is_active,
            'observations_count' => (int) $station->observations_count,
            'first_observation' => $station->observations_min_measured_at,
            'last_observation' => $station->observations_max_measured_at,
            'latitude' => $station->latitude,
            'longitude' => $station->longitude,
        ];
    }

    /**
     * @return list<array{id: int, code: string, label: string, colour: string|null}>
     */
    private function categoryOptions(?string $includeCode = null): array
    {
        return StationCategory::query()
            ->where(function (Builder $query) use ($includeCode): void {
                $query->where('is_active', true);

                if ($includeCode !== null) {
                    $query->orWhere('code', $includeCode);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'code', 'label', 'colour'])
            ->map(fn (StationCategory $category): array => [
                'id' => $category->id,
                'code' => $category->code,
                'label' => $category->label,
                'colour' => $category->colour,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function basinOptions(?int $includeId = null): array
    {
        return Basin::query()
            ->where(function (Builder $query) use ($includeId): void {
                $query->where('is_active', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Basin $basin): array => ['id' => $basin->id, 'code' => $basin->code, 'name' => $basin->name])
            ->all();
    }

    /**
     * @return list<array{id: int, basin_id: int, code: string, name: string}>
     */
    private function subBasinOptions(?int $includeId = null): array
    {
        return SubBasin::query()
            ->where(function (Builder $query) use ($includeId): void {
                $query->where('is_active', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
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
     * @return list<array{id: int, code: string, name: string, symbol: string|null, default_unit_id: int|null}>
     */
    private function parameterOptions(array $includeIds = []): array
    {
        return Parameter::query()
            ->where(function (Builder $query) use ($includeIds): void {
                $query->where('is_active', true);

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->with('defaultUnit:id,code,symbol')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'symbol', 'default_unit_id'])
            ->map(fn (Parameter $parameter): array => [
                'id' => $parameter->id,
                'code' => $parameter->code,
                'name' => $parameter->name,
                'symbol' => $parameter->symbol,
                'default_unit_id' => $parameter->default_unit_id,
                'unit' => $parameter->defaultUnit?->symbol,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, organisation: string|null}>
     */
    private function dataSourceOptions(?int $includeId = null): array
    {
        return DataSource::query()
            ->where(function (Builder $query) use ($includeId): void {
                $query->where('is_active', true);

                if ($includeId !== null) {
                    $query->orWhereKey($includeId);
                }
            })
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
     * @return array<string, mixed>
     */
    private function documentPayload(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'original_name' => $document->original_name,
            'extension' => $document->extension(),
            'mime_type' => $document->mime_type,
            'size_bytes' => (int) $document->size_bytes,
            'uploaded_by' => $document->uploader?->name,
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    private function assertDocumentBelongsTo(MonitoringStation $station, Document $document): void
    {
        abort_unless(
            $document->documentable_type === $station->getMorphClass()
                && (int) $document->documentable_id === (int) $station->getKey(),
            404,
        );
    }
}
