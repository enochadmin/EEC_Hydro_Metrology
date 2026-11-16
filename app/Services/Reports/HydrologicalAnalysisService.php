<?php

namespace App\Services\Reports;

use App\Models\Parameter;
use App\Support\Reports\ParameterProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates observations into the summaries the reports and charts display.
 *
 * The parameter profile decides what "value" means for each bucket: a total for
 * an accumulation parameter, a mean for a state parameter. Minimum, maximum and
 * record counts are always computed and returned so a chart can show the spread
 * without a second query.
 */
class HydrologicalAnalysisService
{
    /**
     * Bucket-by-bucket series for one parameter.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function series(array $filters, string $granularity = 'monthly'): array
    {
        $parameter = $this->parameter($filters);
        $profile = ParameterProfile::for($parameter);
        $limit = (int) config('reports.max_points', 1500);

        $rows = $this->base($filters)
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->selectRaw(
                $this->bucketExpression($granularity).' as bucket, '
                .'COUNT(*) as records, '
                .'SUM(o.value) as total, '
                .'AVG(o.value) as mean, '
                .'MIN(o.value) as minimum, '
                .'MAX(o.value) as maximum'
            )
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $metric = $profile->primaryMetric();

        $points = $rows->map(function ($row) use ($granularity, $profile, $metric) {
            $total = $metric === ParameterProfile::TOTAL
                ? $this->round($row->total, $profile)
                : $this->round($row->mean, $profile);

            return [
                'period' => (string) $row->bucket,
                'label' => $this->label((string) $row->bucket, $granularity),
                'value' => $total,
                'total' => $this->round($row->total, $profile),
                'mean' => $this->round($row->mean, $profile),
                'min' => $this->round($row->minimum, $profile),
                'max' => $this->round($row->maximum, $profile),
                'records' => (int) $row->records,
            ];
        })->all();

        return [
            'granularity' => $granularity,
            'metric' => $metric,
            'metric_label' => $profile->label($metric),
            'points' => $points,
            'truncated' => $truncated,
            'summary' => $this->summary($filters, $profile),
            'profile' => $this->profilePayload($profile),
        ];
    }

    /**
     * Month-of-year profile across every year in range. Answers "which month is
     * wettest" rather than "what happened in March 2019", which the time series
     * already answers.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function seasonalProfile(array $filters): array
    {
        $profile = ParameterProfile::for($this->parameter($filters));

        $rows = $this->base($filters)
            ->groupBy('month_no')
            ->orderBy('month_no')
            ->selectRaw(
                'MONTH(o.measured_at) as month_no, '
                .'COUNT(*) as records, SUM(o.value) as total, AVG(o.value) as mean'
            )
            ->get();

        $metric = $profile->primaryMetric();
        $points = [];

        foreach ($rows as $row) {
            $month = (int) $row->month_no;
            $points[] = [
                'month' => $month,
                'label' => CarbonImmutable::create(2000, $month, 1)->format('M'),
                'value' => $metric === ParameterProfile::TOTAL
                    ? $this->round($row->total, $profile)
                    : $this->round($row->mean, $profile),
                'records' => (int) $row->records,
            ];
        }

        return [
            'metric' => $metric,
            'metric_label' => $profile->label($metric),
            'points' => $points,
        ];
    }

    /**
     * Compare one parameter across stations (dimension = station) or across the
     * basins those stations belong to (dimension = basin).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function comparison(array $filters, string $dimension = 'station'): array
    {
        $profile = ParameterProfile::for($this->parameter($filters));
        $metric = $profile->primaryMetric();

        $query = $this->base($filters);

        if ($dimension === 'basin') {
            $query->join('basins as b', 'b.id', '=', 's.basin_id')
                ->groupBy('b.id', 'b.code', 'b.name')
                ->selectRaw('b.id as group_id, b.code as group_code, b.name as group_name, COUNT(*) as records, SUM(o.value) as total, AVG(o.value) as mean')
                ->orderBy('b.name');
        } else {
            $query->groupBy('s.id', 's.code', 's.name')
                ->selectRaw('s.id as group_id, s.code as group_code, s.name as group_name, COUNT(*) as records, SUM(o.value) as total, AVG(o.value) as mean')
                ->orderByDesc('records')
                ->limit(15);
        }

        return $query->get()->map(fn ($row) => [
            'id' => (int) $row->group_id,
            'code' => (string) $row->group_code,
            'name' => (string) $row->group_name,
            'value' => $metric === ParameterProfile::TOTAL
                ? $this->round($row->total, $profile)
                : $this->round($row->mean, $profile),
            'records' => (int) $row->records,
        ])->all();
    }

    /**
     * Histogram of individual readings plus the headline statistics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function distribution(array $filters): array
    {
        $profile = ParameterProfile::for($this->parameter($filters));
        $stats = $this->base($filters)
            ->selectRaw('COUNT(*) as records, MIN(o.value) as minimum, MAX(o.value) as maximum, AVG(o.value) as mean')
            ->first();

        $records = (int) ($stats->records ?? 0);

        if ($records === 0) {
            return ['bins' => [], 'records' => 0, 'minimum' => null, 'maximum' => null, 'mean' => null];
        }

        $minimum = (float) $stats->minimum;
        $maximum = (float) $stats->maximum;
        $binCount = max(4, (int) config('reports.distribution_bins', 12));
        $width = ($maximum - $minimum) / $binCount;

        if ($width <= 0) {
            return [
                'bins' => [[
                    'label' => $this->round($minimum, $profile),
                    'from' => $this->round($minimum, $profile),
                    'to' => $this->round($maximum, $profile),
                    'records' => $records,
                ]],
                'records' => $records,
                'minimum' => $this->round($minimum, $profile),
                'maximum' => $this->round($maximum, $profile),
                'mean' => $this->round($stats->mean, $profile),
            ];
        }

        $rows = $this->base($filters)
            ->groupBy('bin')
            ->orderBy('bin')
            ->selectRaw('LEAST(FLOOR((o.value - ?) / ?), ?) as bin, COUNT(*) as records', [$minimum, $width, $binCount - 1])
            ->get();

        $counts = $rows->keyBy(fn ($row) => (int) $row->bin);
        $bins = [];

        for ($index = 0; $index < $binCount; $index++) {
            $from = $minimum + $width * $index;
            $to = $index === $binCount - 1 ? $maximum : $from + $width;

            $bins[] = [
                'label' => $this->round($from, $profile).' – '.$this->round($to, $profile),
                'from' => $this->round($from, $profile),
                'to' => $this->round($to, $profile),
                'records' => (int) ($counts[$index]->records ?? 0),
            ];
        }

        return [
            'bins' => $bins,
            'records' => $records,
            'minimum' => $this->round($minimum, $profile),
            'maximum' => $this->round($maximum, $profile),
            'mean' => $this->round($stats->mean, $profile),
        ];
    }

    /**
     * Date span, record counts and quality mix for the current filter.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function coverage(array $filters): array
    {
        $row = $this->base($filters)
            ->selectRaw(
                'COUNT(*) as records, '
                .'COUNT(DISTINCT o.monitoring_station_id) as stations, '
                .'COUNT(DISTINCT DATE(o.measured_at)) as observed_days, '
                .'MIN(o.measured_at) as first_at, '
                .'MAX(o.measured_at) as last_at, '
                .'SUM(CASE WHEN o.quality_status = ? THEN 1 ELSE 0 END) as approved', ['approved']
            )
            ->first();

        $records = (int) ($row->records ?? 0);
        $first = $row->first_at ? CarbonImmutable::parse($row->first_at) : null;
        $last = $row->last_at ? CarbonImmutable::parse($row->last_at) : null;

        $spanDays = $first && $last ? ((int) $first->startOfDay()->diffInDays($last->startOfDay())) + 1 : 0;
        $observedDays = (int) ($row->observed_days ?? 0);
        $missingDays = max(0, $spanDays - $observedDays);

        return [
            'records' => $records,
            'stations' => (int) ($row->stations ?? 0),
            'first_at' => $first?->toDateString(),
            'last_at' => $last?->toDateString(),
            'span_days' => $spanDays,
            'observed_days' => $observedDays,
            'missing_days' => $missingDays,
            'missing_percent' => $spanDays > 0 ? round($missingDays / $spanDays * 100, 1) : null,
            'approved' => (int) ($row->approved ?? 0),
            'approved_percent' => $records > 0 ? round(((int) $row->approved) / $records * 100, 1) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summary(array $filters, ParameterProfile $profile): array
    {
        $row = $this->base($filters)
            ->selectRaw('COUNT(*) as records, SUM(o.value) as total, AVG(o.value) as mean, MIN(o.value) as minimum, MAX(o.value) as maximum')
            ->first();

        return [
            'records' => (int) ($row->records ?? 0),
            'total' => $this->round($row->total ?? null, $profile),
            'mean' => $this->round($row->mean ?? null, $profile),
            'minimum' => $this->round($row->minimum ?? null, $profile),
            'maximum' => $this->round($row->maximum ?? null, $profile),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function parameter(array $filters): ?Parameter
    {
        $id = $filters['parameter_id'] ?? null;

        if (! $id) {
            return null;
        }

        return Parameter::with('defaultUnit')->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function base(array $filters): Builder
    {
        $query = DB::table('hydrological_observations as o')
            ->join('monitoring_stations as s', 's.id', '=', 'o.monitoring_station_id')
            ->where('o.parameter_id', $filters['parameter_id']);

        if (! empty($filters['station_id'])) {
            $query->where('o.monitoring_station_id', $filters['station_id']);
        }

        if (! empty($filters['basin_id'])) {
            $query->where('s.basin_id', $filters['basin_id']);
        }

        if (! empty($filters['sub_basin_id'])) {
            $query->where('s.sub_basin_id', $filters['sub_basin_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('o.measured_at', '>=', $filters['from'].' 00:00:00');
        }

        if (! empty($filters['to'])) {
            $query->where('o.measured_at', '<=', $filters['to'].' 23:59:59');
        }

        if (($filters['quality'] ?? 'approved') === 'approved') {
            $query->where('o.quality_status', 'approved');
        }

        return $query;
    }

    private function bucketExpression(string $granularity): string
    {
        return match ($granularity) {
            'daily' => 'DATE(o.measured_at)',
            'annual' => "DATE_FORMAT(o.measured_at, '%Y-01-01')",
            default => "DATE_FORMAT(o.measured_at, '%Y-%m-01')",
        };
    }

    private function label(string $period, string $granularity): string
    {
        $date = CarbonImmutable::parse($period);

        return match ($granularity) {
            'daily' => $date->format('j M Y'),
            'annual' => $date->format('Y'),
            default => $date->format('M Y'),
        };
    }

    private function round(mixed $value, ParameterProfile $profile): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value, $profile->decimals());
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(ParameterProfile $profile): array
    {
        return [
            'name' => $profile->name(),
            'code' => $profile->code(),
            'unit' => $profile->unitSymbol(),
            'decimals' => $profile->decimals(),
            'accumulates' => $profile->accumulates(),
            'metrics' => $profile->metrics(),
            'primary_metric' => $profile->primaryMetric(),
            'primary_label' => $profile->label($profile->primaryMetric()),
            'descriptor' => $profile->descriptor(),
        ];
    }
}
