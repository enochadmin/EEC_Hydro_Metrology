<?php

namespace App\Services\Reports;

use App\Models\Basin;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Support\Reports\ParameterProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the read-only payloads behind the basin and station reports.
 *
 * Every number here comes from stored records. A basin with no stations reports
 * zero stations and a null date span; it never receives an estimate, and a
 * missing measurement is never rendered as zero.
 */
class ReportQueryService
{
    /**
     * Landing-page payload: what exists, and what can be reported on.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $basins = Basin::query()
            ->withCount(['subBasins', 'stations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'area_km2', 'data_status']);

        return [
            'totals' => [
                'basins' => $basins->count(),
                'sub_basins' => (int) DB::table('sub_basins')->whereNull('deleted_at')->count(),
                'stations' => (int) DB::table('monitoring_stations')->whereNull('deleted_at')->count(),
                'parameters' => (int) DB::table('parameters')->where('is_active', true)->count(),
                'observations' => (int) DB::table('hydrological_observations')->count(),
                'approved' => (int) DB::table('hydrological_observations')->where('quality_status', 'approved')->count(),
            ],
            'span' => $this->globalSpan(),
            'basins' => $basins->map(fn (Basin $basin) => [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
                'area_km2' => $basin->area_km2,
                'data_status' => $basin->data_status,
                'sub_basins' => (int) $basin->sub_basins_count,
                'stations' => (int) $basin->stations_count,
            ])->all(),
            'stations' => $this->stationOptions(),
            'parameters' => $this->parameterOptions(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stationOptions(): array
    {
        return MonitoringStation::query()
            ->with('basin:id,code,name')
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'code', 'name', 'basin_id', 'station_type'])
            ->map(fn (MonitoringStation $station) => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'type' => $station->station_type,
                'basin' => $station->basin?->name,
                'basin_id' => $station->basin_id,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parameterOptions(): array
    {
        return Parameter::query()
            ->with('defaultUnit')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Parameter $parameter) {
                $profile = ParameterProfile::for($parameter);

                return [
                    'id' => $parameter->id,
                    'code' => $parameter->code,
                    'name' => $parameter->name,
                    'category' => $parameter->category,
                    'unit' => $profile->unitSymbol(),
                    'decimals' => $profile->decimals(),
                    'accumulates' => $profile->accumulates(),
                    'primary_metric' => $profile->primaryMetric(),
                    'primary_label' => $profile->label($profile->primaryMetric()),
                    'descriptor' => $profile->descriptor(),
                ];
            })
            ->all();
    }

    /**
     * Full basin report payload.
     *
     * @return array<string, mixed>
     */
    public function basin(Basin $basin): array
    {
        $basin->load(['dataSource:id,name']);

        $subBasins = $basin->subBasins()
            ->withCount('stations')
            ->orderBy('name')
            ->get(['id', 'basin_id', 'code', 'name', 'area_km2', 'centroid_lat', 'centroid_lng', 'data_status']);

        $stations = $basin->stations()
            ->with(['subBasin:id,code,name'])
            ->withCount(['parameters', 'observations'])
            ->withMin('observations', 'measured_at')
            ->withMax('observations', 'measured_at')
            ->orderBy('name')
            ->get();

        $parameters = $this->basinParameterCoverage($basin->id);
        $quality = $this->qualityMix(['basin_id' => $basin->id]);
        $coverage = $this->coverageForBasin($basin->id);

        return [
            'basin' => [
                'id' => $basin->id,
                'code' => $basin->code,
                'name' => $basin->name,
                'name_alt' => $basin->name_alt,
                'description' => $basin->description,
                'area_km2' => $basin->area_km2 === null ? null : (float) $basin->area_km2,
                'elevation_m' => $basin->elevation_m === null ? null : (float) $basin->elevation_m,
                'centroid_lat' => $basin->centroid_lat === null ? null : (float) $basin->centroid_lat,
                'centroid_lng' => $basin->centroid_lng === null ? null : (float) $basin->centroid_lng,
                'data_status' => $basin->data_status,
                'source' => $basin->dataSource?->name,
                'source_note' => $basin->source_note,
                'is_active' => (bool) $basin->is_active,
                'created_at' => $basin->created_at?->toDateString(),
                'updated_at' => $basin->updated_at?->toDateString(),
            ],
            'geography' => $this->geography($basin),
            'sub_basins' => $subBasins->map(fn ($row) => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'area_km2' => $row->area_km2 === null ? null : (float) $row->area_km2,
                'stations' => (int) $row->stations_count,
                'data_status' => $row->data_status,
                'has_centroid' => $row->centroid_lat !== null && $row->centroid_lng !== null,
            ])->all(),
            'stations' => $stations->map(fn (MonitoringStation $station) => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'type' => $station->station_type,
                'sub_basin' => $station->subBasin?->name,
                'parameters' => (int) $station->parameters_count,
                'observations' => (int) $station->observations_count,
                'first_at' => $this->date($station->observations_min_measured_at ?? null),
                'last_at' => $this->date($station->observations_max_measured_at ?? null),
                'is_active' => (bool) $station->is_active,
                'coordinates' => $station->latitude !== null && $station->longitude !== null
                    ? [(float) $station->latitude, (float) $station->longitude]
                    : null,
            ])->all(),
            'parameters' => $parameters,
            'coverage' => $coverage,
            'quality' => $quality,
            'counts' => [
                'sub_basins' => $subBasins->count(),
                'stations' => $stations->count(),
                'parameters' => count($parameters),
            ],
        ];
    }

    /**
     * Full station report payload.
     *
     * @return array<string, mixed>
     */
    public function station(MonitoringStation $station): array
    {
        $station->load([
            'basin:id,code,name',
            'subBasin:id,code,name',
            'watershed:id,code,name',
            'dataSource:id,name',
        ]);

        $parameters = $this->stationParameterCoverage($station->id);
        $quality = $this->qualityMix(['station_id' => $station->id]);
        $coverage = $this->coverageForStation($station->id);

        $recent = $station->observations()
            ->with(['parameter:id,code,name', 'unit:id,symbol'])
            ->orderByDesc('measured_at')
            ->limit(25)
            ->get(['id', 'parameter_id', 'measurement_unit_id', 'measured_at', 'value', 'quality_status', 'record_type', 'series_no']);

        return [
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'name_alt' => $station->name_alt,
                'type' => $station->station_type,
                'basin' => $station->basin?->name,
                'basin_code' => $station->basin?->code,
                'basin_id' => $station->basin_id,
                'sub_basin' => $station->subBasin?->name,
                'watershed' => $station->watershed?->name,
                'latitude' => $station->latitude === null ? null : (float) $station->latitude,
                'longitude' => $station->longitude === null ? null : (float) $station->longitude,
                'elevation_m' => $station->elevation_m === null ? null : (float) $station->elevation_m,
                'operator' => $station->operator,
                'established_on' => $station->established_on?->toDateString(),
                'data_status' => $station->data_status,
                'source' => $station->dataSource?->name,
                'is_active' => (bool) $station->is_active,
                'notes' => $station->notes,
            ],
            'parameters' => $parameters,
            'coverage' => $coverage,
            'quality' => $quality,
            'recent' => $recent->map(fn ($row) => [
                'id' => $row->id,
                'parameter' => $row->parameter?->name,
                'unit' => $row->unit?->symbol,
                'measured_at' => $row->measured_at?->toDateTimeString(),
                'value' => (float) $row->value,
                'quality_status' => $row->quality_status,
                'record_type' => $row->record_type,
            ])->all(),
            'counts' => [
                'parameters' => count($parameters),
                'observations' => $coverage['records'],
            ],
        ];
    }

    /**
     * Geographic features for the GeoJSON export: the basin polygon, its
     * sub-basin polygons and its station points, in EPSG:4326.
     *
     * @return list<array<string, mixed>>
     */
    public function basinFeatures(Basin $basin): array
    {
        $features = [];

        foreach ($basin->boundaries()->orderByDesc('is_primary')->get() as $boundary) {
            $geometry = $this->geometryOf($boundary->getTable(), $boundary->id, 'geom');

            if ($geometry) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'layer' => 'basin_boundary',
                        'basin_code' => $basin->code,
                        'basin_name' => $basin->name,
                        'name' => $boundary->name,
                        'is_primary' => (bool) $boundary->is_primary,
                        'srid' => (int) $boundary->srid,
                    ],
                ];
            }
        }

        foreach ($basin->subBasins()->orderBy('name')->get() as $subBasin) {
            $geometry = $this->geometryOf($subBasin->getTable(), $subBasin->id, 'boundary');

            if ($geometry) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'layer' => 'sub_basin',
                        'code' => $subBasin->code,
                        'name' => $subBasin->name,
                        'basin_code' => $basin->code,
                        'area_km2' => $subBasin->area_km2 === null ? null : (float) $subBasin->area_km2,
                    ],
                ];
            }
        }

        foreach ($basin->stations()->get() as $station) {
            if ($station->latitude === null || $station->longitude === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $station->longitude, (float) $station->latitude],
                ],
                'properties' => [
                    'layer' => 'monitoring_station',
                    'code' => $station->code,
                    'name' => $station->name,
                    'type' => $station->station_type,
                    'data_status' => $station->data_status,
                    'basin_code' => $basin->code,
                ],
            ];
        }

        return $features;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stationFeatures(MonitoringStation $station): array
    {
        $features = [];

        if ($station->latitude !== null && $station->longitude !== null) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $station->longitude, (float) $station->latitude],
                ],
                'properties' => [
                    'layer' => 'monitoring_station',
                    'code' => $station->code,
                    'name' => $station->name,
                    'type' => $station->station_type,
                    'basin' => $station->basin?->name,
                    'sub_basin' => $station->subBasin?->name,
                    'elevation_m' => $station->elevation_m === null ? null : (float) $station->elevation_m,
                    'data_status' => $station->data_status,
                ],
            ];
        }

        return $features;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function basinParameterCoverage(int $basinId): array
    {
        $rows = DB::table('hydrological_observations as o')
            ->join('monitoring_stations as s', 's.id', '=', 'o.monitoring_station_id')
            ->join('parameters as p', 'p.id', '=', 'o.parameter_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', 'p.default_unit_id')
            ->where('s.basin_id', $basinId)
            ->whereNull('s.deleted_at')
            ->groupBy('p.id', 'p.code', 'p.name', 'p.value_type', 'p.decimals', 'u.symbol')
            ->orderBy('p.name')
            ->selectRaw(
                'p.id, p.code, p.name, p.value_type, p.decimals, u.symbol as unit, '
                .'COUNT(*) as records, COUNT(DISTINCT o.monitoring_station_id) as stations, '
                .'COUNT(DISTINCT DATE(o.measured_at)) as observed_days, '
                .'MIN(o.measured_at) as first_at, MAX(o.measured_at) as last_at'
            )
            ->get();

        return $rows->map(fn ($row) => $this->coverageRow((array) $row))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stationParameterCoverage(int $stationId): array
    {
        $rows = DB::table('station_parameters as sp')
            ->join('parameters as p', 'p.id', '=', 'sp.parameter_id')
            ->leftJoin('measurement_units as u', function ($join) {
                $join->on('u.id', '=', DB::raw('coalesce(sp.measurement_unit_id, p.default_unit_id)'));
            })
            ->leftJoin('hydrological_observations as o', function ($join) use ($stationId) {
                $join->on('o.parameter_id', '=', 'sp.parameter_id')
                    ->where('o.monitoring_station_id', '=', $stationId);
            })
            ->where('sp.monitoring_station_id', $stationId)
            ->groupBy('p.id', 'p.code', 'p.name', 'p.value_type', 'p.decimals', 'u.symbol', 'sp.is_active', 'sp.starts_on', 'sp.ends_on')
            ->orderBy('p.name')
            ->selectRaw(
                'p.id, p.code, p.name, p.value_type, p.decimals, u.symbol as unit, sp.is_active, '
                .'COUNT(o.id) as records, 1 as stations, '
                .'COUNT(DISTINCT DATE(o.measured_at)) as observed_days, '
                .'MIN(o.measured_at) as first_at, MAX(o.measured_at) as last_at'
            )
            ->get();

        return $rows->map(function ($row) {
            $coverage = $this->coverageRow((array) $row);
            $coverage['is_active'] = (bool) $row->is_active;

            return $coverage;
        })->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function coverageRow(array $row): array
    {
        $first = $row['first_at'] ? CarbonImmutable::parse($row['first_at']) : null;
        $last = $row['last_at'] ? CarbonImmutable::parse($row['last_at']) : null;
        $spanDays = $first && $last ? ((int) $first->startOfDay()->diffInDays($last->startOfDay())) + 1 : 0;
        $observedDays = (int) ($row['observed_days'] ?? 0);
        $records = (int) ($row['records'] ?? 0);
        $missing = max(0, $spanDays - $observedDays);

        return [
            'parameter_id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'unit' => $row['unit'] ?? null,
            'value_type' => (string) $row['value_type'],
            'records' => $records,
            'stations' => (int) ($row['stations'] ?? 0),
            'first_at' => $first?->toDateString(),
            'last_at' => $last?->toDateString(),
            'span_days' => $spanDays,
            'observed_days' => $observedDays,
            'missing_days' => $missing,
            'missing_percent' => $spanDays > 0 ? round($missing / $spanDays * 100, 1) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function qualityMix(array $scope): array
    {
        $query = DB::table('hydrological_observations as o')
            ->join('monitoring_stations as s', 's.id', '=', 'o.monitoring_station_id');

        if (! empty($scope['basin_id'])) {
            $query->where('s.basin_id', $scope['basin_id']);
        }

        if (! empty($scope['station_id'])) {
            $query->where('o.monitoring_station_id', $scope['station_id']);
        }

        $row = $query->selectRaw(
            'COUNT(*) as records, '
            .'SUM(CASE WHEN o.quality_status = ? THEN 1 ELSE 0 END) as approved, '
            .'SUM(CASE WHEN o.quality_status = ? THEN 1 ELSE 0 END) as pending, '
            .'SUM(CASE WHEN o.quality_status = ? THEN 1 ELSE 0 END) as flagged, '
            .'SUM(CASE WHEN o.quality_status = ? THEN 1 ELSE 0 END) as rejected',
            ['approved', 'pending', 'flagged', 'rejected']
        )->first();

        $issues = DB::table('data_quality_issues')
            ->when(! empty($scope['basin_id']), fn ($query) => $query->whereIn(
                'monitoring_station_id',
                DB::table('monitoring_stations')->select('id')->where('basin_id', $scope['basin_id'])
            ))
            ->when(! empty($scope['station_id']), fn ($query) => $query->where('monitoring_station_id', $scope['station_id']))
            ->where('status', 'open')
            ->count();

        $records = (int) ($row->records ?? 0);

        return [
            'records' => $records,
            'approved' => (int) ($row->approved ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'flagged' => (int) ($row->flagged ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
            'approved_percent' => $records > 0 ? round(((int) $row->approved) / $records * 100, 1) : null,
            'open_issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coverageForBasin(int $basinId): array
    {
        return $this->span(
            DB::table('hydrological_observations as o')
                ->join('monitoring_stations as s', 's.id', '=', 'o.monitoring_station_id')
                ->where('s.basin_id', $basinId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function coverageForStation(int $stationId): array
    {
        return $this->span(
            DB::table('hydrological_observations as o')->where('o.monitoring_station_id', $stationId)
        );
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array<string, mixed>
     */
    private function span($query): array
    {
        $row = $query->selectRaw(
            'COUNT(*) as records, COUNT(DISTINCT DATE(o.measured_at)) as observed_days, '
            .'MIN(o.measured_at) as first_at, MAX(o.measured_at) as last_at'
        )->first();

        $first = $row?->first_at ? CarbonImmutable::parse($row->first_at) : null;
        $last = $row?->last_at ? CarbonImmutable::parse($row->last_at) : null;
        $spanDays = $first && $last ? ((int) $first->startOfDay()->diffInDays($last->startOfDay())) + 1 : 0;

        return [
            'records' => (int) ($row->records ?? 0),
            'first_at' => $first?->toDateString(),
            'last_at' => $last?->toDateString(),
            'span_days' => $spanDays,
            'observed_days' => (int) ($row->observed_days ?? 0),
            'missing_days' => max(0, $spanDays - (int) ($row->observed_days ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function geography(Basin $basin): array
    {
        $boundary = $basin->boundaries()->orderByDesc('is_primary')->orderByDesc('id')->first();

        $vertices = null;
        $bounds = null;

        if ($boundary) {
            $meta = DB::table('basin_boundaries')->where('id', $boundary->id)
                ->selectRaw('ST_NumPoints(geom) as vertices')
                ->first();
            $vertices = $meta?->vertices !== null ? (int) $meta->vertices : null;

            $bounds = $this->boundsOf($this->geometryOf('basin_boundaries', $boundary->id, 'geom'));
        }

        return [
            'has_boundary' => $boundary !== null,
            'boundary_name' => $boundary?->name,
            'boundary_srid' => $boundary?->srid,
            'boundary_accuracy_m' => $boundary?->accuracy_m === null ? null : (float) $boundary->accuracy_m,
            'boundary_effective_from' => $boundary?->effective_from?->toDateString(),
            'boundary_vertices' => $vertices,
            'centroid' => $basin->centroid_lat !== null && $basin->centroid_lng !== null
                ? [(float) $basin->centroid_lat, (float) $basin->centroid_lng]
                : null,
            'area_km2' => $basin->area_km2 === null ? null : (float) $basin->area_km2,
            'elevation_m' => $basin->elevation_m === null ? null : (float) $basin->elevation_m,
            'bounds' => $bounds,
        ];
    }

    /**
     * Bounding box of a GeoJSON geometry, used for the report header. Returns
     * null when the geometry has no coordinates.
     *
     * @param  array<string, mixed>|null  $geometry
     * @return array<string, float>|null
     */
    private function boundsOf(?array $geometry): ?array
    {
        if (! $geometry) {
            return null;
        }

        $lng = [];
        $lat = [];

        $walk = function ($coordinates) use (&$walk, &$lng, &$lat) {
            if (! is_array($coordinates)) {
                return;
            }

            if (count($coordinates) === 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
                $lng[] = (float) $coordinates[0];
                $lat[] = (float) $coordinates[1];

                return;
            }

            foreach ($coordinates as $child) {
                $walk($child);
            }
        };

        $walk($geometry['coordinates'] ?? []);

        if ($lng === [] || $lat === []) {
            return null;
        }

        return [
            'west' => round(min($lng), 6),
            'south' => round(min($lat), 6),
            'east' => round(max($lng), 6),
            'north' => round(max($lat), 6),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function geometryOf(string $table, int $id, string $column): ?array
    {
        $row = DB::table($table)->where('id', $id)
            ->selectRaw('ST_AsGeoJSON('.$column.', 6) as geojson')
            ->first();

        if (! $row || ! $row->geojson) {
            return null;
        }

        return json_decode($row->geojson, true);
    }

    private function date(?string $value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->toDateString() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function globalSpan(): array
    {
        $row = DB::table('hydrological_observations')
            ->selectRaw('COUNT(*) as records, MIN(measured_at) as first_at, MAX(measured_at) as last_at')
            ->first();

        return [
            'first_at' => $row?->first_at ? CarbonImmutable::parse($row->first_at)->toDateString() : null,
            'last_at' => $row?->last_at ? CarbonImmutable::parse($row->last_at)->toDateString() : null,
            'records' => (int) ($row->records ?? 0),
        ];
    }
}
