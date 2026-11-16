<?php

namespace App\Services\Gis;

use App\Models\Basin;
use App\Models\GeoFeature;
use App\Models\HydrologicalObservation;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use App\Support\GeoJson\GeoJsonConverter;
use App\Support\GeoJson\GeoJsonSimplifier;
use Illuminate\Support\Facades\DB;

/**
 * Builds the GeoJSON payloads consumed by the map.
 *
 * Everything returned here is trimmed for display: no observations are sent,
 * geometry is simplified server-side, and station records carry only the
 * summary needed to draw a marker. Station parameters and the observation
 * period are fetched separately when a marker is opened.
 */
class GisDataService
{
    public function __construct(private readonly GisLayerService $layers) {}

    public function tolerance(mixed $requested): float
    {
        $min = (float) config('gis.simplification.min');
        $max = (float) config('gis.simplification.max');
        $value = is_numeric($requested)
            ? (float) $requested
            : (float) config('gis.simplification.detail');

        return max($min, min($max, $value));
    }

    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function basins(float $tolerance): array
    {
        $rows = DB::table('basins')
            ->whereNull('deleted_at')
            ->whereNotNull('boundary')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'name_alt',
                'area_km2',
                'data_status',
                'centroid_lat',
                'centroid_lng',
                DB::raw('ST_AsGeoJSON(boundary, 6) as geojson'),
                DB::raw('(select count(*) from sub_basins where sub_basins.basin_id = basins.id and sub_basins.deleted_at is null) as sub_basins_count'),
                DB::raw('(select count(*) from monitoring_stations where monitoring_stations.basin_id = basins.id and monitoring_stations.deleted_at is null) as stations_count'),
            ]);

        $colors = $this->layers->basinColors();
        $features = [];

        foreach ($rows as $row) {
            $geometry = $this->decodeGeometry($row->geojson, $tolerance);

            if ($geometry === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'name_alt' => $row->name_alt,
                    'area_km2' => $row->area_km2,
                    'centroid_lat' => $row->centroid_lat !== null ? (float) $row->centroid_lat : null,
                    'centroid_lng' => $row->centroid_lng !== null ? (float) $row->centroid_lng : null,
                    'data_status' => $row->data_status,
                    'sub_basins_count' => (int) $row->sub_basins_count,
                    'stations_count' => (int) $row->stations_count,
                    'color' => $colors[$row->id] ?? config('gis.basin_palette')[0],
                    'href' => '/basins/'.$row->id,
                ],
            ];
        }

        return $this->collection($features);
    }

    /**
     * @return array<string, mixed>
     */
    public function basinDetail(Basin $basin, float $tolerance): array
    {
        $envelope = DB::table('basins')
            ->where('id', $basin->id)
            ->selectRaw('ST_AsText(ST_Envelope(boundary)) as envelope')
            ->first();

        $bbox = GeoJsonConverter::bboxFromWkt($envelope->envelope ?? null);
        $colors = $this->layers->basinColors();
        $color = $colors[$basin->id] ?? config('gis.basin_palette')[0];

        $subBasinRows = DB::table('sub_basins')
            ->where('basin_id', $basin->id)
            ->whereNull('deleted_at')
            ->whereNotNull('boundary')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'area_km2',
                'data_status',
                DB::raw('ST_AsGeoJSON(boundary, 6) as geojson'),
                DB::raw('(select count(*) from monitoring_stations where monitoring_stations.sub_basin_id = sub_basins.id and monitoring_stations.deleted_at is null) as stations_count'),
            ]);

        $subBasinFeatures = [];

        foreach ($subBasinRows as $row) {
            $geometry = $this->decodeGeometry($row->geojson, $tolerance);

            if ($geometry === null) {
                continue;
            }

            $subBasinFeatures[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'area_km2' => $row->area_km2,
                    'data_status' => $row->data_status,
                    'stations_count' => (int) $row->stations_count,
                    'basin_id' => $basin->id,
                    'color' => $color,
                    'href' => '/sub-basins/'.$row->id,
                ],
            ];
        }

        return [
            'basin' => [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
                'name_alt' => $basin->name_alt,
                'area_km2' => $basin->area_km2,
                'centroid_lat' => $basin->centroid_lat !== null ? (float) $basin->centroid_lat : null,
                'centroid_lng' => $basin->centroid_lng !== null ? (float) $basin->centroid_lng : null,
                'elevation_m' => $basin->elevation_m !== null ? (float) $basin->elevation_m : null,
                'data_status' => $basin->data_status,
                'data_source' => $basin->dataSource?->name,
                'sub_basins_count' => $basin->subBasins()->count(),
                'stations_count' => $basin->stations()->count(),
                'color' => $color,
                'bbox' => $bbox,
                'href' => '/basins/'.$basin->id,
            ],
            'sub_basins' => $this->collection($subBasinFeatures),
            'stations' => $this->stationFeatures(['basin_id' => $basin->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subBasinDetail(SubBasin $subBasin, float $tolerance): array
    {
        $envelope = DB::table('sub_basins')
            ->where('id', $subBasin->id)
            ->selectRaw('ST_AsText(ST_Envelope(boundary)) as envelope')
            ->first();

        $colors = $this->layers->basinColors();

        return [
            'sub_basin' => [
                'id' => $subBasin->id,
                'code' => $subBasin->code,
                'name' => $subBasin->name,
                'name_alt' => $subBasin->name_alt,
                'area_km2' => $subBasin->area_km2,
                'centroid_lat' => $subBasin->centroid_lat !== null ? (float) $subBasin->centroid_lat : null,
                'centroid_lng' => $subBasin->centroid_lng !== null ? (float) $subBasin->centroid_lng : null,
                'elevation_m' => $subBasin->elevation_m,
                'data_status' => $subBasin->data_status,
                'basin' => $subBasin->basin?->only(['id', 'code', 'name']),
                'stations_count' => $subBasin->stations()->count(),
                'color' => $colors[$subBasin->basin_id] ?? config('gis.basin_palette')[0],
                'bbox' => GeoJsonConverter::bboxFromWkt($envelope->envelope ?? null),
                'href' => '/sub-basins/'.$subBasin->id,
            ],
            'stations' => $this->stationFeatures(['sub_basin_id' => $subBasin->id]),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{type: string, features: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function stationFeatures(array $filters = []): array
    {
        $max = (int) config('gis.limits.max_station_points');

        $query = MonitoringStation::query()
            ->with(['basin:id,code,name', 'subBasin:id,code,name'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name');

        if (! empty($filters['types']) && is_array($filters['types'])) {
            $query->whereIn('station_type', $filters['types']);
        }

        if (! empty($filters['basin_id'])) {
            $query->where('basin_id', $filters['basin_id']);
        }

        if (! empty($filters['sub_basin_id'])) {
            $query->where('sub_basin_id', $filters['sub_basin_id']);
        }

        $stations = $query->limit($max + 1)->get();
        $truncated = $stations->count() > $max;

        if ($truncated) {
            $stations = $stations->take($max);
        }

        $features = [];

        foreach ($stations as $station) {
            $type = config('gis.station_types.'.$station->station_type);

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $station->longitude, (float) $station->latitude],
                ],
                'properties' => [
                    'id' => $station->id,
                    'code' => $station->code,
                    'name' => $station->name,
                    'station_type' => $station->station_type,
                    'type_label' => $type['label'] ?? $station->station_type,
                    'group' => $type['group'] ?? 'Other',
                    'color' => $type['color'] ?? '#64748B',
                    'icon' => $type['icon'] ?? 'circle',
                    'elevation_m' => $station->elevation_m !== null ? (float) $station->elevation_m : null,
                    'operator' => $station->operator,
                    'data_status' => $station->data_status,
                    'is_active' => $station->is_active,
                    'basin' => $station->basin?->only(['id', 'code', 'name']),
                    'sub_basin' => $station->subBasin?->only(['id', 'code', 'name']),
                ],
            ];
        }

        return [
            ...$this->collection($features),
            'meta' => [
                'count' => count($features),
                'truncated' => $truncated,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stationDetail(MonitoringStation $station): array
    {
        $station->load([
            'basin:id,code,name',
            'subBasin:id,code,name',
            'watershed:id,code,name',
            'dataSource:id,name',
        ]);

        $type = config('gis.station_types.'.$station->station_type);

        $parameters = $station->stationParameters()
            ->with(['parameter:id,code,name', 'unit:id,code,name,symbol'])
            ->orderBy('id')
            ->get()
            ->map(fn ($stationParameter): array => [
                'id' => $stationParameter->id,
                'code' => $stationParameter->parameter?->code,
                'name' => $stationParameter->parameter?->name,
                'unit' => $stationParameter->unit?->symbol ?? $stationParameter->unit?->code,
                'sensor_reference' => $stationParameter->sensor_reference,
                'starts_on' => $stationParameter->starts_on?->toDateString(),
                'ends_on' => $stationParameter->ends_on?->toDateString(),
                'is_active' => $stationParameter->is_active,
            ]);

        $availability = HydrologicalObservation::query()
            ->where('monitoring_station_id', $station->id)
            ->selectRaw('COUNT(*) as total, MIN(measured_at) as first_at, MAX(measured_at) as last_at')
            ->first();

        return [
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'name_alt' => $station->name_alt,
                'station_type' => $station->station_type,
                'type_label' => $type['label'] ?? $station->station_type,
                'group' => $type['group'] ?? 'Other',
                'color' => $type['color'] ?? '#64748B',
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'elevation_m' => $station->elevation_m,
                'operator' => $station->operator,
                'established_on' => $station->established_on?->toDateString(),
                'data_status' => $station->data_status,
                'is_active' => $station->is_active,
                'basin' => $station->basin?->only(['id', 'code', 'name']),
                'sub_basin' => $station->subBasin?->only(['id', 'code', 'name']),
                'watershed' => $station->watershed?->only(['id', 'code', 'name']),
                'data_source' => $station->dataSource?->name,
            ],
            'parameters' => $parameters,
            'availability' => [
                'records' => (int) ($availability->total ?? 0),
                'first_at' => $availability?->first_at,
                'last_at' => $availability?->last_at,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function features(string $layer, float $tolerance): array
    {
        $type = match ($layer) {
            'national_boundary' => GeoFeature::LAYER_NATIONAL_BOUNDARY,
            'rivers' => GeoFeature::LAYER_RIVER,
            'lakes' => GeoFeature::LAYER_LAKE,
            default => null,
        };

        if ($type === null) {
            return $this->collection([]);
        }

        $rows = GeoFeature::query()
            ->where('layer_type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'layer_type',
                'basin_id',
                'source_note',
                'min_lng',
                'min_lat',
                'max_lng',
                'max_lat',
                DB::raw('ST_AsGeoJSON(geom, 6) as geojson'),
            ]);

        $colors = [
            GeoFeature::LAYER_NATIONAL_BOUNDARY => '#0F172A',
            GeoFeature::LAYER_RIVER => '#0284C7',
            GeoFeature::LAYER_LAKE => '#0EA5E9',
        ];

        $features = [];

        foreach ($rows as $row) {
            $geometry = $this->decodeGeometry($row->geojson, $tolerance);

            if ($geometry === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'layer_type' => $row->layer_type,
                    'basin_id' => $row->basin_id,
                    'source_note' => $row->source_note,
                    'color' => $colors[$type] ?? '#334155',
                    'bbox' => [
                        'min_lng' => $row->min_lng,
                        'min_lat' => $row->min_lat,
                        'max_lng' => $row->max_lng,
                        'max_lat' => $row->max_lat,
                    ],
                ],
            ];
        }

        return $this->collection($features);
    }

    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function watersheds(float $tolerance): array
    {
        $rows = DB::table('watersheds')
            ->join('sub_basins', 'sub_basins.id', '=', 'watersheds.sub_basin_id')
            ->whereNull('watersheds.deleted_at')
            ->whereNotNull('watersheds.boundary')
            ->orderBy('watersheds.name')
            ->get([
                'watersheds.id',
                'watersheds.code',
                'watersheds.name',
                'watersheds.area_km2',
                'watersheds.data_status',
                'sub_basins.basin_id',
                'sub_basins.code as sub_basin_code',
                'sub_basins.name as sub_basin_name',
                DB::raw('ST_AsGeoJSON(watersheds.boundary, 6) as geojson'),
            ]);

        $colors = $this->layers->basinColors();
        $features = [];

        foreach ($rows as $row) {
            $geometry = $this->decodeGeometry($row->geojson, $tolerance);

            if ($geometry === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'area_km2' => $row->area_km2,
                    'data_status' => $row->data_status,
                    'basin_id' => (int) $row->basin_id,
                    'sub_basin' => [
                        'code' => $row->sub_basin_code,
                        'name' => $row->sub_basin_name,
                    ],
                    'color' => $colors[$row->basin_id] ?? '#475569',
                ],
            ];
        }

        return $this->collection($features);
    }

    /**
     * Station GeoJSON with aggregated rainfall values for a date range.
     *
     * Returns one Feature per station that has at least one observation in the
     * period. Each feature carries value (mm total or mean, depending on the
     * parameter), days_with_data, missing_days and completeness_pct so the
     * front-end can drive graduated markers and popup mini-charts.
     *
     * @return array{type: string, features: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function stationsRainfall(string $from, string $to, string $parameterCode): array
    {
        $rows = DB::table('monitoring_stations as ms')
            ->join('hydrological_observations as ho', 'ho.monitoring_station_id', '=', 'ms.id')
            ->join('parameters as p', 'p.id', '=', 'ho.parameter_id')
            ->whereNull('ms.deleted_at')
            ->whereNotNull('ms.latitude')
            ->whereNotNull('ms.longitude')
            ->where('p.code', $parameterCode)
            ->whereBetween('ho.measured_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('ms.id', 'ms.code', 'ms.name', 'ms.latitude', 'ms.longitude', 'ms.station_type', 'ms.basin_id')
            ->select([
                'ms.id',
                'ms.code',
                'ms.name',
                'ms.latitude',
                'ms.longitude',
                'ms.station_type',
                'ms.basin_id',
                DB::raw('SUM(ho.value) as total_value'),
                DB::raw('AVG(ho.value) as avg_value'),
                DB::raw('COUNT(DISTINCT DATE(ho.measured_at)) as days_with_data'),
            ])
            ->orderBy('total_value', 'desc')
            ->get();

        // Calculate expected days in the period for completeness %.
        $expectedDays = max(1, (int) round(
            (strtotime($to) - strtotime($from)) / 86400 + 1
        ));

        $stationTypes = config('gis.station_types');
        $features = [];
        $maxValue = 0;

        foreach ($rows as $row) {
            $totalValue = round((float) ($row->total_value ?? 0), 2);

            if ($totalValue > $maxValue) {
                $maxValue = $totalValue;
            }
        }

        foreach ($rows as $row) {
            $totalValue = round((float) ($row->total_value ?? 0), 2);
            $daysWithData = (int) ($row->days_with_data ?? 0);
            $missingDays = max(0, $expectedDays - $daysWithData);
            $completenessPct = $expectedDays > 0 ? round(($daysWithData / $expectedDays) * 100, 1) : 0;
            $type = $stationTypes[$row->station_type] ?? [];

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row->longitude, (float) $row->latitude],
                ],
                'properties' => [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'station_type' => $row->station_type,
                    'type_label' => $type['label'] ?? $row->station_type,
                    'color' => $type['color'] ?? '#64748B',
                    'value' => $totalValue,
                    'avg_value' => round((float) ($row->avg_value ?? 0), 3),
                    'days_with_data' => $daysWithData,
                    'missing_days' => $missingDays,
                    'completeness_pct' => $completenessPct,
                    'max_value' => $maxValue,
                    'parameter_code' => $parameterCode,
                ],
            ];
        }

        return [
            ...$this->collection($features),
            'meta' => [
                'count' => count($features),
                'from' => $from,
                'to' => $to,
                'parameter_code' => $parameterCode,
                'max_value' => $maxValue,
                'expected_days' => $expectedDays,
            ],
        ];
    }

    /**
     * Per-station monthly observation series for the popup mini-chart.
     *
     * @return array<string, mixed>
     */
    public function stationMonthlySeries(MonitoringStation $station, string $from, string $to, string $parameterCode): array
    {
        $rows = DB::table('hydrological_observations as ho')
            ->join('parameters as p', 'p.id', '=', 'ho.parameter_id')
            ->where('ho.monitoring_station_id', $station->id)
            ->where('p.code', $parameterCode)
            ->whereBetween('ho.measured_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupByRaw('YEAR(ho.measured_at), MONTH(ho.measured_at)')
            ->selectRaw('YEAR(ho.measured_at) as yr, MONTH(ho.measured_at) as mo, SUM(ho.value) as total, COUNT(*) as days')
            ->orderByRaw('yr ASC, mo ASC')
            ->get();

        $series = $rows->map(fn ($row): array => [
            'year' => (int) $row->yr,
            'month' => (int) $row->mo,
            'label' => date('M Y', mktime(0, 0, 0, (int) $row->mo, 1, (int) $row->yr)),
            'total' => round((float) ($row->total ?? 0), 2),
            'days' => (int) $row->days,
        ])->all();

        return [
            'station_id' => $station->id,
            'parameter_code' => $parameterCode,
            'from' => $from,
            'to' => $to,
            'series' => $series,
        ];
    }

    /**
     * Cross-layer search used by the map search box.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $basins = Basin::query()
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'code', 'name', 'centroid_lat', 'centroid_lng', 'data_status'])
            ->map(fn (Basin $basin): array => [
                'type' => 'basin',
                'id' => $basin->id,
                'label' => $basin->name,
                'sublabel' => $basin->code,
                'lat' => $basin->centroid_lat !== null ? (float) $basin->centroid_lat : null,
                'lng' => $basin->centroid_lng !== null ? (float) $basin->centroid_lng : null,
                'data_status' => $basin->data_status,
            ]);

        $subBasins = SubBasin::query()
            ->with('basin:id,code,name')
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'basin_id', 'code', 'name', 'centroid_lat', 'centroid_lng', 'data_status'])
            ->map(fn (SubBasin $subBasin): array => [
                'type' => 'sub_basin',
                'id' => $subBasin->id,
                'label' => $subBasin->name,
                'sublabel' => trim(($subBasin->code ?? '').' · '.($subBasin->basin?->name ?? ''), ' ·'),
                'lat' => $subBasin->centroid_lat !== null ? (float) $subBasin->centroid_lat : null,
                'lng' => $subBasin->centroid_lng !== null ? (float) $subBasin->centroid_lng : null,
                'basin_id' => $subBasin->basin_id,
                'data_status' => $subBasin->data_status,
            ]);

        $stations = MonitoringStation::query()
            ->with('basin:id,code,name')
            ->where(fn ($query) => $query->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'basin_id', 'code', 'name', 'latitude', 'longitude', 'station_type', 'data_status'])
            ->map(fn (MonitoringStation $station): array => [
                'type' => 'station',
                'id' => $station->id,
                'label' => $station->name,
                'sublabel' => trim(($station->code ?? '').' · '.($station->basin?->name ?? ''), ' ·'),
                'lat' => $station->latitude !== null ? (float) $station->latitude : null,
                'lng' => $station->longitude !== null ? (float) $station->longitude : null,
                'station_type' => $station->station_type,
                'basin_id' => $station->basin_id,
                'data_status' => $station->data_status,
            ]);

        $featureTypeLabels = [
            GeoFeature::LAYER_NATIONAL_BOUNDARY => 'National boundary',
            GeoFeature::LAYER_RIVER => 'River',
            GeoFeature::LAYER_LAKE => 'Lake',
        ];

        $features = GeoFeature::query()
            ->where('is_active', true)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'layer_type', 'name', 'min_lng', 'min_lat', 'max_lng', 'max_lat'])
            ->map(fn (GeoFeature $feature): array => [
                'type' => 'feature',
                'id' => $feature->id,
                'label' => $feature->name,
                'sublabel' => $featureTypeLabels[$feature->layer_type] ?? $feature->layer_type,
                'layer' => $feature->layer_type === GeoFeature::LAYER_RIVER ? 'rivers' : ($feature->layer_type === GeoFeature::LAYER_LAKE ? 'lakes' : 'national_boundary'),
                'lat' => $feature->min_lat !== null && $feature->max_lat !== null ? ((float) $feature->min_lat + (float) $feature->max_lat) / 2 : null,
                'lng' => $feature->min_lng !== null && $feature->max_lng !== null ? ((float) $feature->min_lng + (float) $feature->max_lng) / 2 : null,
            ]);

        return [
            ...$basins->all(),
            ...$subBasins->all(),
            ...$stations->all(),
            ...$features->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $geojson
     * @return array<string, mixed>|null
     */
    private function decodeGeometry(mixed $geojson, float $tolerance): ?array
    {
        if (! is_string($geojson) || $geojson === '') {
            return null;
        }

        $decoded = json_decode($geojson, true);

        if (! is_array($decoded)) {
            return null;
        }

        return GeoJsonSimplifier::simplify($decoded, $tolerance);
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    private function collection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
