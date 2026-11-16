<?php

namespace App\Http\Controllers\Basins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Basins\ImportBoundaryRequest;
use App\Http\Requests\Basins\StoreDocumentRequest;
use App\Http\Requests\Basins\StoreSubBasinRequest;
use App\Http\Requests\Basins\UpdateSubBasinRequest;
use App\Models\ActivityLog;
use App\Models\Basin;
use App\Models\DataSource;
use App\Models\Document;
use App\Models\HydrologicalObservation;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use App\Services\Documents\DocumentService;
use App\Services\Geography\BoundaryService;
use App\Services\Geography\SubBasinService;
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
 * Sub-basin registry. Every record belongs to exactly one parent basin.
 */
class SubBasinController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SubBasin::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'basin_id' => ['nullable', 'integer', 'exists:basins,id'],
            'status' => ['nullable', 'in:pending,provisional,verified'],
            'state' => ['nullable', 'in:active,inactive'],
            'boundary' => ['nullable', 'in:mapped,pending'],
            'sort' => ['nullable', 'in:code,name,area_km2,stations_count,data_status,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'code';
        $direction = $filters['direction'] ?? 'asc';

        $subBasins = $this->filteredQuery($filters)
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SubBasin $subBasin): array => $this->listPayload($subBasin));

        return Inertia::render('SubBasins/Index', [
            'subBasins' => $subBasins,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'basin_id' => $filters['basin_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'state' => $filters['state'] ?? '',
                'boundary' => $filters['boundary'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'basins' => $this->basinOptions(),
            'summary' => [
                'total' => SubBasin::query()->count(),
                'mapped' => SubBasin::query()->whereNotNull('boundary')->count(),
                'pending' => SubBasin::query()->whereNull('boundary')->count(),
                'stations' => MonitoringStation::query()->count(),
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', SubBasin::class);

        $defaultBasinId = $request->integer('basin_id') ?: null;

        abort_if(
            $defaultBasinId !== null && ! Basin::query()->whereKey($defaultBasinId)->exists(),
            404,
        );

        return Inertia::render('SubBasins/Create', [
            'basins' => $this->basinOptions(),
            'defaultBasinId' => $defaultBasinId,
            'dataSources' => $this->dataSourceOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(StoreSubBasinRequest $request, SubBasinService $subBasins): RedirectResponse
    {
        $subBasin = $subBasins->create($request->validated(), $request->user());

        return redirect()
            ->route('sub-basins.show', $subBasin)
            ->with('success', 'Sub-basin '.$subBasin->code.' created under '.$subBasin->basin->code.'.');
    }

    public function show(Request $request, SubBasin $subBasin): Response
    {
        Gate::authorize('view', $subBasin);

        $subBasin->load(['basin:id,code,name', 'dataSource:id,name,organisation']);

        $stations = $subBasin->stations()
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MonitoringStation $station): array => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'station_type' => $station->station_type,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'elevation_m' => $station->elevation_m,
                'data_status' => $station->data_status,
                'is_active' => $station->is_active,
            ]);

        $documents = $subBasin->documents()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(fn (Document $document): array => $this->documentPayload($document));

        $activity = ActivityLog::query()
            ->where('subject_type', $subBasin->getMorphClass())
            ->where('subject_id', $subBasin->getKey())
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

        $geometry = $this->geometry($subBasin->id);

        $observations = HydrologicalObservation::query()
            ->whereIn('monitoring_station_id', $subBasin->stations()->select('id'))
            ->count();

        return Inertia::render('SubBasins/Show', [
            'subBasin' => [
                'id' => $subBasin->id,
                'code' => $subBasin->code,
                'name' => $subBasin->name,
                'name_alt' => $subBasin->name_alt,
                'description' => $subBasin->description,
                'area_km2' => $subBasin->area_km2,
                'centroid_lat' => $subBasin->centroid_lat,
                'centroid_lng' => $subBasin->centroid_lng,
                'elevation_m' => $subBasin->elevation_m,
                'data_status' => $subBasin->data_status,
                'source_note' => $subBasin->source_note,
                'is_active' => $subBasin->is_active,
                'basin' => $subBasin->basin?->only(['id', 'code', 'name']),
                'data_source' => $subBasin->dataSource?->only(['id', 'name', 'organisation']),
                'boundary_status' => $geometry === null ? 'pending' : 'mapped',
                'created_at' => $subBasin->created_at?->toIso8601String(),
                'updated_at' => $subBasin->updated_at?->toIso8601String(),
            ],
            'stations' => $stations,
            'geometry' => $geometry,
            'documents' => $documents,
            'activity' => $activity,
            'summary' => [
                'stations' => $subBasin->stations()->count(),
                'observations' => $observations,
                'documents' => $documents->count(),
            ],
            'abilities' => [
                'update' => Gate::allows('update', $subBasin),
                'delete' => Gate::allows('delete', $subBasin),
                'importBoundary' => Gate::allows('importBoundary', $subBasin),
                'manageDocuments' => Gate::allows('manageDocuments', $subBasin),
            ],
        ]);
    }

    public function edit(Request $request, SubBasin $subBasin): Response
    {
        Gate::authorize('update', $subBasin);

        $subBasin->loadCount('stations');

        return Inertia::render('SubBasins/Edit', [
            'subBasin' => [
                'id' => $subBasin->id,
                'basin_id' => $subBasin->basin_id,
                'code' => $subBasin->code,
                'name' => $subBasin->name,
                'name_alt' => $subBasin->name_alt,
                'description' => $subBasin->description,
                'area_km2' => $subBasin->area_km2,
                'centroid_lat' => $subBasin->centroid_lat,
                'centroid_lng' => $subBasin->centroid_lng,
                'elevation_m' => $subBasin->elevation_m,
                'data_status' => $subBasin->data_status,
                'source_note' => $subBasin->source_note,
                'is_active' => $subBasin->is_active,
                'data_source_id' => $subBasin->data_source_id,
                'stations_count' => (int) $subBasin->stations_count,
            ],
            'basins' => $this->basinOptions(),
            'dataSources' => $this->dataSourceOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateSubBasinRequest $request, SubBasin $subBasin, SubBasinService $subBasins): RedirectResponse
    {
        $subBasins->update($subBasin, $request->validated(), $request->user());

        return redirect()
            ->route('sub-basins.show', $subBasin)
            ->with('success', 'Sub-basin '.$subBasin->code.' updated.');
    }

    public function destroy(Request $request, SubBasin $subBasin, SubBasinService $subBasins): RedirectResponse
    {
        Gate::authorize('delete', $subBasin);

        $subBasins->delete($subBasin, $request->user());

        return redirect()
            ->route('sub-basins.index')
            ->with('success', 'Sub-basin '.$subBasin->code.' deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', SubBasin::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'basin_id' => ['nullable', 'integer', 'exists:basins,id'],
            'status' => ['nullable', 'in:pending,provisional,verified'],
            'state' => ['nullable', 'in:active,inactive'],
            'boundary' => ['nullable', 'in:mapped,pending'],
        ]);

        $query = $this->filteredQuery($filters)->orderBy('code');

        $filename = 'eec-sub-basins-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Code',
                'Sub-basin name',
                'Parent basin',
                'Area (km2)',
                'Monitoring stations',
                'Boundary status',
                'Data status',
                'Record status',
                'Centroid latitude',
                'Centroid longitude',
                'Mean elevation (m)',
                'Created at',
            ], ',', '"', '\\');

            $query->chunk(200, function ($subBasins) use ($handle): void {
                foreach ($subBasins as $subBasin) {
                    fputcsv($handle, [
                        $subBasin->code,
                        $subBasin->name,
                        $subBasin->basin?->name,
                        $subBasin->area_km2,
                        (int) $subBasin->stations_count,
                        $subBasin->has_boundary ? 'mapped' : 'pending',
                        $subBasin->data_status,
                        $subBasin->is_active ? 'active' : 'inactive',
                        $subBasin->centroid_lat,
                        $subBasin->centroid_lng,
                        $subBasin->elevation_m,
                        $subBasin->created_at?->toDateTimeString(),
                    ], ',', '"', '\\');
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeBoundary(ImportBoundaryRequest $request, SubBasin $subBasin, BoundaryService $boundaries): RedirectResponse
    {
        $result = $boundaries->importForSubBasin(
            $subBasin,
            $request->safe()->except('file'),
            $request->file('file'),
            $request->user(),
        );

        return redirect()
            ->route('sub-basins.show', $subBasin)
            ->with('success', 'Boundary imported ('.$result['type'].', '.$result['positions'].' positions, SRID 4326).');
    }

    public function destroyBoundary(Request $request, SubBasin $subBasin, BoundaryService $boundaries): RedirectResponse
    {
        Gate::authorize('importBoundary', $subBasin);

        $boundaries->clearForSubBasin($subBasin, $request->user());

        return back()->with('success', 'Boundary removed from '.$subBasin->code.'.');
    }

    public function storeDocument(StoreDocumentRequest $request, SubBasin $subBasin, DocumentService $documents): RedirectResponse
    {
        $document = $documents->store($subBasin, $request->file('file'), $request->validated('title'), $request->user());

        return back()->with('success', 'Document "'.$document->title.'" uploaded.');
    }

    public function downloadDocument(SubBasin $subBasin, Document $document): StreamedResponse
    {
        Gate::authorize('view', $subBasin);

        $this->assertDocumentBelongsTo($subBasin, $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'The stored file is missing.');

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(Request $request, SubBasin $subBasin, Document $document, DocumentService $documents): RedirectResponse
    {
        Gate::authorize('manageDocuments', $subBasin);

        $this->assertDocumentBelongsTo($subBasin, $document);

        $documents->delete($document, $request->user());

        return back()->with('success', 'Document removed.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SubBasin>
     */
    private function filteredQuery(array $filters): Builder
    {
        $search = $filters['search'] ?? null;
        $basinId = $filters['basin_id'] ?? null;
        $status = $filters['status'] ?? null;
        $state = $filters['state'] ?? null;
        $boundary = $filters['boundary'] ?? null;

        return SubBasin::query()
            ->select([
                'sub_basins.id',
                'sub_basins.basin_id',
                'sub_basins.code',
                'sub_basins.name',
                'sub_basins.name_alt',
                'sub_basins.area_km2',
                'sub_basins.centroid_lat',
                'sub_basins.centroid_lng',
                'sub_basins.elevation_m',
                'sub_basins.data_status',
                'sub_basins.is_active',
                'sub_basins.data_source_id',
                'sub_basins.created_at',
                'sub_basins.updated_at',
            ])
            ->selectRaw('(sub_basins.boundary is not null) as has_boundary')
            ->with('basin:id,code,name')
            ->withCount('stations')
            ->when($search, function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('name_alt', 'like', "%{$term}%");
                });
            })
            ->when($basinId, fn (Builder $query, int|string $value) => $query->where('basin_id', $value))
            ->when($status, fn (Builder $query, string $value) => $query->where('data_status', $value))
            ->when($state === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($state === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($boundary === 'mapped', fn (Builder $query) => $query->whereNotNull('boundary'))
            ->when($boundary === 'pending', fn (Builder $query) => $query->whereNull('boundary'));
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(SubBasin $subBasin): array
    {
        return [
            'id' => $subBasin->id,
            'code' => $subBasin->code,
            'name' => $subBasin->name,
            'name_alt' => $subBasin->name_alt,
            'basin' => $subBasin->basin?->only(['id', 'code', 'name']),
            'area_km2' => $subBasin->area_km2,
            'elevation_m' => $subBasin->elevation_m,
            'data_status' => $subBasin->data_status,
            'is_active' => $subBasin->is_active,
            'stations_count' => (int) $subBasin->stations_count,
            'boundary_status' => $subBasin->has_boundary ? 'mapped' : 'pending',
            'centroid_lat' => $subBasin->centroid_lat,
            'centroid_lng' => $subBasin->centroid_lng,
            'created_at' => $subBasin->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{preview: string|null, length: int|null, bbox: array<string, float>|null}|null
     */
    private function geometry(int $subBasinId): ?array
    {
        $row = DB::table('sub_basins')
            ->where('id', $subBasinId)
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

    private function assertDocumentBelongsTo(SubBasin $subBasin, Document $document): void
    {
        abort_unless(
            $document->documentable_type === $subBasin->getMorphClass()
                && (int) $document->documentable_id === (int) $subBasin->getKey(),
            404,
        );
    }

    /**
     * @return Collection<int, array{id: int, code: string, name: string}>
     */
    private function basinOptions(): Collection
    {
        return Basin::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Basin $basin): array => [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
            ]);
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
}
