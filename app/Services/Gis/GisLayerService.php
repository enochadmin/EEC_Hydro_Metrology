<?php

namespace App\Services\Gis;

use App\Models\Basin;
use App\Models\GeoFeature;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use App\Models\Watershed;
use Illuminate\Support\Facades\DB;

/**
 * Describes which map layers actually have data.
 *
 * A layer is only offered in the map controls when its dataset exists. Raster
 * layers stay declared but unavailable until a verified surface is registered,
 * so the UI can explain the gap instead of drawing an empty tile layer.
 */
class GisLayerService
{
    /**
     * @return array{
     *     layers: list<array<string, mixed>>,
     *     station_types: list<array<string, mixed>>,
     *     basin_legend: list<array<string, mixed>>,
     *     has_geometry: bool,
     *     counts: array<string, int>
     * }
     */
    public function overview(): array
    {
        $counts = [
            'basins' => Basin::query()->whereNotNull('boundary')->count(),
            'sub_basins' => SubBasin::query()->whereNotNull('boundary')->count(),
            'watersheds' => Watershed::query()->whereNotNull('boundary')->count(),
            'national_boundary' => GeoFeature::query()
                ->where('layer_type', GeoFeature::LAYER_NATIONAL_BOUNDARY)
                ->where('is_active', true)
                ->count(),
            'rivers' => GeoFeature::query()->where('layer_type', GeoFeature::LAYER_RIVER)->where('is_active', true)->count(),
            'lakes' => GeoFeature::query()->where('layer_type', GeoFeature::LAYER_LAKE)->where('is_active', true)->count(),
            'stations' => MonitoringStation::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count(),
        ];

        $layers = [];

        foreach (config('gis.layers') as $key => $definition) {
            $count = $counts[$key] ?? 0;
            $declaredAvailable = $definition['available'] ?? true;
            $available = $declaredAvailable && $count > 0;

            $layers[] = [
                'key' => $key,
                'label' => $definition['label'],
                'group' => $definition['group'],
                'geometry' => $definition['geometry'],
                'description' => $definition['description'],
                'count' => $count,
                'available' => $available,
                'reason' => $available
                    ? null
                    : ($definition['reason'] ?? 'No dataset has been imported for this layer yet.'),
            ];
        }

        return [
            'layers' => $layers,
            'station_types' => $this->stationTypes(),
            'basin_legend' => $this->basinLegend(),
            'has_geometry' => (bool) (
                $counts['basins']
                + $counts['sub_basins']
                + $counts['watersheds']
                + $counts['national_boundary']
                + $counts['rivers']
                + $counts['lakes']
                + ($counts['stations'] ?? 0)
            ),
            'counts' => $counts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stationTypes(): array
    {
        $counts = MonitoringStation::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('station_type, count(*) as total')
            ->groupBy('station_type')
            ->pluck('total', 'station_type');

        $types = [];

        foreach (config('gis.station_types') as $key => $definition) {
            $types[] = [
                'key' => $key,
                'label' => $definition['label'],
                'group' => $definition['group'],
                'color' => $definition['color'],
                'icon' => $definition['icon'],
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $types;
    }

    /**
     * Stable colours for the legend and the polygons, assigned in registry order.
     *
     * @return list<array<string, mixed>>
     */
    public function basinLegend(): array
    {
        $palette = config('gis.basin_palette');
        $paletteCount = count($palette);

        $basins = Basin::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'area_km2', 'data_status']);

        $mapped = DB::table('basins')
            ->whereNull('deleted_at')
            ->whereNotNull('boundary')
            ->pluck('id')
            ->all();

        $legend = [];

        foreach ($basins->values() as $index => $basin) {
            $legend[] = [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
                'area_km2' => $basin->area_km2,
                'data_status' => $basin->data_status,
                'color' => $palette[$index % $paletteCount],
                'has_boundary' => in_array($basin->id, $mapped, true),
            ];
        }

        return $legend;
    }

    /**
     * @return array<int, string>
     */
    public function basinColors(): array
    {
        $colors = [];

        foreach ($this->basinLegend() as $entry) {
            $colors[$entry['id']] = $entry['color'];
        }

        return $colors;
    }
}
