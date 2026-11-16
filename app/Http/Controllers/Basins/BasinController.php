<?php

namespace App\Http\Controllers\Basins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Basins\ImportBoundaryRequest;
use App\Http\Requests\Basins\StoreBasinRequest;
use App\Http\Requests\Basins\StoreDocumentRequest;
use App\Http\Requests\Basins\UpdateBasinRequest;
use App\Models\ActivityLog;
use App\Models\Basin;
use App\Models\BasinBoundary;
use App\Models\DataSource;
use App\Models\Document;
use App\Models\HydrologicalObservation;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use App\Services\Documents\DocumentService;
use App\Services\Geography\BasinService;
use App\Services\Geography\BoundaryService;
use App\Support\GeoJson\GeoJsonConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Basin registry.
 *
 * Listing, creation, editing and deletion go through the BasinPolicy; boundary
 * imports use a separate ability because the GIS Officer may upload polygons
 * without holding registry-edit rights.
 */
class BasinController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Basin::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,provisional,verified'],
            'state' => ['nullable', 'in:active,inactive'],
            'boundary' => ['nullable', 'in:mapped,pending'],
            'sort' => ['nullable', 'in:code,name,area_km2,sub_basins_count,stations_count,data_status,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';

        $basins = $this->filteredQuery($filters)
            ->with('dataSource:id,name')
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Basin $basin): array => $this->listPayload($basin));

        return Inertia::render('Basins/Index', [
            'basins' => $basins,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'state' => $filters['state'] ?? '',
                'boundary' => $filters['boundary'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'total' => Basin::query()->count(),
                'verified' => Basin::query()->where('data_status', 'verified')->count(),
                'archived' => Basin::onlyTrashed()->count(),
                'boundary_mapped' => Basin::query()
                    ->whereHas('boundaries', fn (Builder $query) => $query->where('is_primary', true))
                    ->count(),
                'boundary_pending' => Basin::query()->whereDoesntHave('boundaries')->count(),
                'sub_basins' => SubBasin::query()->count(),
                'stations' => MonitoringStation::query()->count(),
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Basin::class);

        return Inertia::render('Basins/Create', [
            'dataSources' => $this->dataSourceOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(StoreBasinRequest $request, BasinService $basins): RedirectResponse
    {
        $basin = $basins->create($request->validated(), $request->user());

        return redirect()
            ->route('basins.show', $basin)
            ->with('success', 'Basin '.$basin->code.' created.');
    }

    public function show(Request $request, Basin $basin): Response
    {
        Gate::authorize('view', $basin);

        $basin->load(['dataSource:id,name,organisation']);

        $subBasinIds = $basin->subBasins()->orderBy('name')->pluck('id');
        $mappedSubBasinIds = DB::table('sub_basins')->whereIn('id', $subBasinIds)->whereNotNull('boundary')->pluck('id')->all();

        $subBasins = $basin->subBasins()
            ->withCount('stations')
            ->orderBy('name')
            ->get()
            ->map(fn (SubBasin $subBasin): array => [
                'id' => $subBasin->id,
                'code' => $subBasin->code,
                'name' => $subBasin->name,
                'name_alt' => $subBasin->name_alt,
                'area_km2' => $subBasin->area_km2,
                'elevation_m' => $subBasin->elevation_m,
                'stations_count' => (int) $subBasin->stations_count,
                'data_status' => $subBasin->data_status,
                'is_active' => $subBasin->is_active,
                'boundary_status' => in_array($subBasin->id, $mappedSubBasinIds, true) ? 'mapped' : 'pending',
            ]);

        $stations = $basin->stations()
            ->with('subBasin:id,code,name')
            ->orderBy('name')
            ->paginate(10, ['*'], 'station_page')
            ->withQueryString()
            ->through(fn (MonitoringStation $station): array => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'station_type' => $station->station_type,
                'sub_basin' => $station->subBasin?->only(['id', 'code', 'name']),
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'elevation_m' => $station->elevation_m,
                'data_status' => $station->data_status,
                'is_active' => $station->is_active,
            ]);

        $observationsQuery = HydrologicalObservation::query()
            ->whereIn('monitoring_station_id', $basin->stations()->select('id'));

        $observations = (clone $observationsQuery)
            ->with(['station:id,code,name', 'parameter:id,code,name', 'unit:id,symbol'])
            ->orderByDesc('measured_at')
            ->limit(12)
            ->get()
            ->map(fn (HydrologicalObservation $observation): array => [
                'id' => $observation->id,
                'station' => $observation->station?->only(['id', 'code', 'name']),
                'parameter' => $observation->parameter?->only(['id', 'code', 'name']),
                'unit' => $observation->unit?->symbol,
                'measured_at' => $observation->measured_at?->toIso8601String(),
                'value' => $observation->value,
                'quality_status' => $observation->quality_status,
            ]);

        $boundaries = $this->boundaryRows($basin);

        $documents = $basin->documents()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(fn (Document $document): array => $this->documentPayload($document));

        $activity = $this->activityFor($basin, $subBasinIds->all());

        $basin->loadCount(['subBasins', 'stations', 'boundaries']);

        return Inertia::render('Basins/Show', [
            'basin' => [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
                'name_alt' => $basin->name_alt,
                'description' => $basin->description,
                'area_km2' => $basin->area_km2,
                'centroid_lat' => $basin->centroid_lat,
                'centroid_lng' => $basin->centroid_lng,
                'elevation_m' => $basin->elevation_m,
                'data_status' => $basin->data_status,
                'source_note' => $basin->source_note,
                'is_active' => $basin->is_active,
                'data_source' => $basin->dataSource?->only(['id', 'name', 'organisation']),
                'boundary_status' => $this->boundaryStatus(
                    $basin->boundaries()->where('is_primary', true)->exists(),
                    $basin->boundaries_count,
                ),
                'created_at' => $basin->created_at?->toIso8601String(),
                'updated_at' => $basin->updated_at?->toIso8601String(),
            ],
            'subBasins' => $subBasins,
            'stations' => $stations,
            'observations' => $observations,
            'boundaries' => $boundaries,
            'displayBoundary' => $this->displayGeometry($basin->id),
            'documents' => $documents,
            'activity' => $activity,
            'summary' => [
                'sub_basins' => $subBasinIds->count(),
                'stations' => $basin->stations()->count(),
                'observations' => $observationsQuery->count(),
                'boundaries' => $boundaries->count(),
                'documents' => $documents->count(),
            ],
            'abilities' => [
                'update' => Gate::allows('update', $basin),
                'delete' => Gate::allows('delete', $basin),
                'importBoundary' => Gate::allows('importBoundary', $basin),
                'manageDocuments' => Gate::allows('manageDocuments', $basin),
            ],
        ]);
    }

    public function edit(Request $request, Basin $basin): Response
    {
        Gate::authorize('update', $basin);

        $basin->loadCount(['subBasins', 'stations', 'boundaries']);

        return Inertia::render('Basins/Edit', [
            'basin' => [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
                'name_alt' => $basin->name_alt,
                'description' => $basin->description,
                'area_km2' => $basin->area_km2,
                'centroid_lat' => $basin->centroid_lat,
                'centroid_lng' => $basin->centroid_lng,
                'elevation_m' => $basin->elevation_m,
                'data_status' => $basin->data_status,
                'source_note' => $basin->source_note,
                'is_active' => $basin->is_active,
                'data_source_id' => $basin->data_source_id,
                'boundary_status' => $this->boundaryStatus(
                    $basin->boundaries()->where('is_primary', true)->exists(),
                    $basin->boundaries_count,
                ),
                'sub_basins_count' => (int) $basin->sub_basins_count,
                'stations_count' => (int) $basin->stations_count,
            ],
            'dataSources' => $this->dataSourceOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateBasinRequest $request, Basin $basin, BasinService $basins): RedirectResponse
    {
        $basins->update($basin, $request->validated(), $request->user());

        return redirect()
            ->route('basins.show', $basin)
            ->with('success', 'Basin '.$basin->code.' updated.');
    }

    public function destroy(Request $request, Basin $basin, BasinService $basins): RedirectResponse
    {
        Gate::authorize('delete', $basin);

        $basins->delete($basin, $request->user());

        return redirect()
            ->route('basins.index')
            ->with('success', 'Basin '.$basin->code.' deleted. It can be restored by a system administrator.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Basin::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,provisional,verified'],
            'state' => ['nullable', 'in:active,inactive'],
            'boundary' => ['nullable', 'in:mapped,pending'],
        ]);

        $query = $this->filteredQuery($filters)
            ->with('dataSource:id,name')
            ->orderBy('code');

        $filename = 'eec-basins-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code',
                'Basin name',
                'Alternative name',
                'Area (km2)',
                'Sub-basins',
                'Monitoring stations',
                'Boundary status',
                'Data status',
                'Record status',
                'Data source',
                'Centroid latitude',
                'Centroid longitude',
                'Mean elevation (m)',
                'Created at',
            ], ',', '"', '\\');

            $query->chunk(200, function ($basins) use ($handle): void {
                foreach ($basins as $basin) {
                    fputcsv($handle, [
                        $basin->code,
                        $basin->name,
                        $basin->name_alt,
                        $basin->area_km2,
                        (int) $basin->sub_basins_count,
                        (int) $basin->stations_count,
                        $this->boundaryStatus((bool) $basin->has_primary_boundary, (int) $basin->boundaries_count),
                        $basin->data_status,
                        $basin->is_active ? 'active' : 'inactive',
                        $basin->dataSource?->name,
                        $basin->centroid_lat,
                        $basin->centroid_lng,
                        $basin->elevation_m,
                        $basin->created_at?->toDateTimeString(),
                    ], ',', '"', '\\');
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeBoundary(ImportBoundaryRequest $request, Basin $basin, BoundaryService $boundaries): RedirectResponse
    {
        $boundary = $boundaries->importForBasin(
            $basin,
            $request->safe()->except('file'),
            $request->file('file'),
            $request->user(),
        );

        return redirect()
            ->route('basins.show', $basin)
            ->with('success', 'Boundary "'.$boundary->name.'" imported as '.($boundary->is_primary ? 'primary' : 'reference').' polygon.');
    }

    public function destroyBoundary(Request $request, Basin $basin, BasinBoundary $boundary, BoundaryService $boundaries): RedirectResponse
    {
        Gate::authorize('importBoundary', $basin);

        abort_unless((int) $boundary->basin_id === (int) $basin->getKey(), 404);

        $boundaries->deleteForBasin($basin, $boundary, $request->user());

        return back()->with('success', 'Boundary removed.');
    }

    public function storeDocument(StoreDocumentRequest $request, Basin $basin, DocumentService $documents): RedirectResponse
    {
        $document = $documents->store($basin, $request->file('file'), $request->validated('title'), $request->user());

        return back()->with('success', 'Document "'.$document->title.'" uploaded.');
    }

    public function downloadDocument(Basin $basin, Document $document): StreamedResponse
    {
        Gate::authorize('view', $basin);

        $this->assertDocumentBelongsTo($basin, $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'The stored file is missing.');

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(Request $request, Basin $basin, Document $document, DocumentService $documents): RedirectResponse
    {
        Gate::authorize('manageDocuments', $basin);

        $this->assertDocumentBelongsTo($basin, $document);

        $documents->delete($document, $request->user());

        return back()->with('success', 'Document removed.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Basin>
     */
    private function filteredQuery(array $filters): Builder
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $state = $filters['state'] ?? null;
        $boundary = $filters['boundary'] ?? null;

        return Basin::query()
            ->select([
                'basins.id',
                'basins.code',
                'basins.name',
                'basins.name_alt',
                'basins.area_km2',
                'basins.centroid_lat',
                'basins.centroid_lng',
                'basins.elevation_m',
                'basins.data_status',
                'basins.is_active',
                'basins.data_source_id',
                'basins.source_note',
                'basins.created_at',
                'basins.updated_at',
            ])
            ->withCount(['subBasins', 'stations', 'boundaries'])
            ->withExists(['boundaries as has_primary_boundary' => fn (Builder $query) => $query->where('is_primary', true)])
            ->when($search, function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('name_alt', 'like', "%{$term}%");
                });
            })
            ->when($status, fn (Builder $query, string $value) => $query->where('data_status', $value))
            ->when($state === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($state === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($boundary === 'mapped', fn (Builder $query) => $query->whereHas('boundaries', fn (Builder $query) => $query->where('is_primary', true)))
            ->when($boundary === 'pending', fn (Builder $query) => $query->whereDoesntHave('boundaries'));
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(Basin $basin): array
    {
        return [
            'id' => $basin->id,
            'code' => $basin->code,
            'name' => $basin->name,
            'name_alt' => $basin->name_alt,
            'area_km2' => $basin->area_km2,
            'elevation_m' => $basin->elevation_m,
            'centroid_lat' => $basin->centroid_lat,
            'centroid_lng' => $basin->centroid_lng,
            'data_status' => $basin->data_status,
            'is_active' => $basin->is_active,
            'sub_basins_count' => (int) $basin->sub_basins_count,
            'stations_count' => (int) $basin->stations_count,
            'boundary_versions' => (int) $basin->boundaries_count,
            'boundary_status' => $this->boundaryStatus((bool) $basin->has_primary_boundary, (int) $basin->boundaries_count),
            'data_source' => $basin->dataSource?->only(['id', 'name']),
            'created_at' => $basin->created_at?->toIso8601String(),
        ];
    }

    private function boundaryStatus(bool $hasPrimary, int $versions): string
    {
        if ($hasPrimary) {
            return 'mapped';
        }

        return $versions > 0 ? 'unverified' : 'pending';
    }

    /**
     * Boundary versions plus a truncated WKT preview and envelope bounding box.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function boundaryRows(Basin $basin): Collection
    {
        return DB::table('basin_boundaries')
            ->leftJoin('data_sources', 'data_sources.id', '=', 'basin_boundaries.source_id')
            ->where('basin_boundaries.basin_id', $basin->id)
            ->whereNull('basin_boundaries.deleted_at')
            ->orderByDesc('basin_boundaries.is_primary')
            ->orderByDesc('basin_boundaries.created_at')
            ->get([
                'basin_boundaries.id',
                'basin_boundaries.name',
                'basin_boundaries.srid',
                'basin_boundaries.accuracy_m',
                'basin_boundaries.is_primary',
                'basin_boundaries.effective_from',
                'basin_boundaries.effective_to',
                'basin_boundaries.notes',
                'basin_boundaries.created_at',
                'data_sources.name as source_name',
                DB::raw('LEFT(ST_AsText(basin_boundaries.geom), 600) as wkt_preview'),
                DB::raw('ST_AsText(ST_Envelope(basin_boundaries.geom)) as envelope_wkt'),
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'source' => $row->source_name,
                'srid' => (int) $row->srid,
                'accuracy_m' => $row->accuracy_m,
                'is_primary' => (bool) $row->is_primary,
                'effective_from' => $row->effective_from,
                'effective_to' => $row->effective_to,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
                'wkt_preview' => $row->wkt_preview,
                'bbox' => GeoJsonConverter::bboxFromWkt($row->envelope_wkt),
            ]);
    }

    /**
     * @return array{preview: string|null, length: int|null, bbox: array<string, float>|null}|null
     */
    private function displayGeometry(int $basinId): ?array
    {
        $row = DB::table('basins')
            ->where('id', $basinId)
            ->selectRaw('LEFT(ST_AsText(boundary), 600) as preview, LENGTH(ST_AsText(boundary)) as length, ST_AsText(ST_Envelope(boundary)) as envelope')
            ->first();

        if ($row === null || $row->preview === null) {
            return null;
        }

        return [
            'preview' => $row->preview,
            'length' => $row->length !== null ? (int) $row->length : null,
            'bbox' => GeoJsonConverter::bboxFromWkt($row->envelope),
        ];
    }

    /**
     * @param  list<int>  $subBasinIds
     * @return Collection<int, array<string, mixed>>
     */
    private function activityFor(Basin $basin, array $subBasinIds): Collection
    {
        return ActivityLog::query()
            ->where(function ($query) use ($basin, $subBasinIds): void {
                $query->where(function ($query) use ($basin): void {
                    $query->where('subject_type', $basin->getMorphClass())
                        ->where('subject_id', $basin->getKey());
                });

                if ($subBasinIds !== []) {
                    $query->orWhere(function ($query) use ($subBasinIds): void {
                        $query->where('subject_type', (new SubBasin)->getMorphClass())
                            ->whereIn('subject_id', $subBasinIds);
                    });
                }
            })
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'user' => $log->user?->name,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);
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
            'extension' => strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION)) ?: 'file',
            'mime_type' => $document->mime_type,
            'size_bytes' => (int) $document->size_bytes,
            'uploaded_by' => $document->uploader?->name,
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    private function assertDocumentBelongsTo(Basin $basin, Document $document): void
    {
        abort_unless(
            $document->documentable_type === $basin->getMorphClass()
                && (int) $document->documentable_id === (int) $basin->getKey(),
            404,
        );
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function statusOptions(): array
    {
        return [
            [
                'value' => 'pending',
                'label' => 'Pending',
                'description' => 'Registered, but area, coordinates or boundary still need an official source.',
            ],
            [
                'value' => 'provisional',
                'label' => 'Provisional',
                'description' => 'Recorded from a field sheet or secondary source and awaiting confirmation.',
            ],
            [
                'value' => 'verified',
                'label' => 'Verified',
                'description' => 'Confirmed against an official EEC or survey source.',
            ],
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, organisation: string|null}>
     */
    private function dataSourceOptions(): Collection
    {
        return DataSource::query()
            ->orderBy('name')
            ->get(['id', 'name', 'organisation'])
            ->map(fn (DataSource $source): array => [
                'id' => $source->id,
                'name' => $source->name,
                'organisation' => $source->organisation,
            ]);
    }
}
