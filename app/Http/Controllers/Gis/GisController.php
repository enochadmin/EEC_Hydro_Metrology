<?php

namespace App\Http\Controllers\Gis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gis\StoreGeoFeatureRequest;
use App\Models\Basin;
use App\Models\GeoFeature;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use App\Services\Gis\GeoFeatureImportService;
use App\Services\Gis\GisDataService;
use App\Services\Gis\GisLayerService;
use App\Support\Gis\BasemapRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Interactive GIS surface.
 *
 * The Inertia page carries only metadata (layer availability, legend, station
 * types). Geometry is pulled from the JSON endpoints below, so the first paint
 * does not wait for polygons and detailed records are fetched on selection.
 */
class GisController extends Controller
{
    public function __construct(
        private readonly GisLayerService $layers,
        private readonly GisDataService $data,
        private readonly GeoFeatureImportService $imports,
        private readonly BasemapRegistry $basemaps,
    ) {}

    public function index(Request $request): Response
    {
        $overview = $this->layers->overview();

        $importable = array_values(array_filter(
            $overview['layers'],
            fn (array $layer): bool => in_array($layer['key'], ['national_boundary', 'rivers', 'lakes'], true),
        ));

        return Inertia::render('Gis/Index', [
            'layers' => $overview['layers'],
            'station_types' => $overview['station_types'],
            'basin_legend' => $overview['basin_legend'],
            'has_geometry' => $overview['has_geometry'],
            'counts' => $overview['counts'],
            'importable_layers' => $importable,
            'basemaps' => $this->basemaps->all(),
            'default_basemap' => $this->basemaps->defaultId(),
            'basemap_status' => $this->basemaps->status(),
            'initial' => [
                'basin' => $request->integer('basin') ?: null,
                'sub_basin' => $request->integer('sub_basin') ?: null,
                'station' => $request->integer('station') ?: null,
                'from' => $request->filled('from') ? $request->input('from') : null,
                'to' => $request->filled('to') ? $request->input('to') : null,
                'parameter' => $request->input('parameter', 'rainfall'),
            ],
            'map' => [
                'center' => config('eec.map.center'),
                'zoom' => config('eec.map.zoom'),
                'min_zoom' => 3,
                'max_zoom' => 18,
            ],
            'abilities' => [
                'import' => $request->user()?->can('manage geographic boundaries') ?? false,
            ],
        ]);
    }

    public function basins(Request $request): JsonResponse
    {
        return response()->json($this->data->basins($this->tolerance($request)));
    }

    public function basin(Request $request, Basin $basin): JsonResponse
    {
        Gate::authorize('view', $basin);

        $basin->load('dataSource:id,name');

        return response()->json($this->data->basinDetail($basin, $this->tolerance($request)));
    }

    public function subBasin(Request $request, SubBasin $subBasin): JsonResponse
    {
        Gate::authorize('view', $subBasin);

        $subBasin->load('basin:id,code,name');

        return response()->json($this->data->subBasinDetail($subBasin, $this->tolerance($request)));
    }

    public function stations(Request $request): JsonResponse
    {
        if (is_string($request->input('types'))) {
            $request->merge([
                'types' => array_values(array_filter(explode(',', (string) $request->input('types')))),
            ]);
        }

        $validated = $request->validate([
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(array_keys(config('gis.station_types')))],
            'classes' => ['nullable', 'array'],
            'classes.*' => ['string', Rule::in(array_keys(config('stations.classes')))],
            'basin_id' => ['nullable', 'integer'],
            'sub_basin_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->data->stationFeatures([
            'types' => $validated['types'] ?? [],
            'classes' => $validated['classes'] ?? [],
            'basin_id' => $validated['basin_id'] ?? null,
            'sub_basin_id' => $validated['sub_basin_id'] ?? null,
        ]));
    }

    public function station(Request $request, MonitoringStation $station): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'parameter' => ['nullable', 'string', 'max:60'],
        ]);

        return response()->json($this->data->stationDetail(
            $station,
            $validated['date'] ?? null,
            $validated['parameter'] ?? 'rainfall',
        ));
    }

    public function stationsRainfall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'parameter' => ['nullable', 'string', 'max:60'],
        ]);

        $parameterCode = $validated['parameter'] ?? 'rainfall';

        return response()->json(
            $this->data->stationsRainfall($validated['from'], $validated['to'], $parameterCode)
        );
    }

    public function stationSeries(Request $request, MonitoringStation $station): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'parameter' => ['nullable', 'string', 'max:60'],
        ]);

        $parameterCode = $validated['parameter'] ?? 'rainfall';

        return response()->json(
            $this->data->stationMonthlySeries($station, $validated['from'], $validated['to'], $parameterCode)
        );
    }

    public function features(Request $request, string $layer): JsonResponse
    {
        return response()->json($this->data->features($layer, $this->tolerance($request)));
    }

    public function watersheds(Request $request): JsonResponse
    {
        return response()->json($this->data->watersheds($this->tolerance($request)));
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json(['results' => $this->data->search($validated['q'])]);
    }

    public function storeFeature(StoreGeoFeatureRequest $request): RedirectResponse
    {
        $result = $this->imports->import($request->validated(), $request->file('file'), $request->user());

        return back()->with(
            'success',
            $result['features'].' '.($result['features'] === 1 ? 'feature' : 'features').' imported into the map layer.',
        );
    }

    public function destroyFeature(Request $request, GeoFeature $feature): RedirectResponse
    {
        abort_unless($request->user()?->can('manage geographic boundaries'), 403);

        $this->imports->delete($feature, $request->user());

        return back()->with('success', 'Map feature removed.');
    }

    private function tolerance(Request $request): float
    {
        $validated = $request->validate([
            'tolerance' => ['nullable', 'numeric', 'between:0,0.05'],
        ]);

        return $this->data->tolerance($validated['tolerance'] ?? null);
    }
}
