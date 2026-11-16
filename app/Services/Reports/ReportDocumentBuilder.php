<?php

namespace App\Services\Reports;

use App\Support\Reports\ParameterProfile;
use Illuminate\Support\Str;

/**
 * Turns a report payload into one neutral document structure.
 *
 * CSV, Excel and PDF all render the same structure, so a value can never be
 * correct in one format and stale in another. Numeric columns are already
 * formatted here, which keeps the renderers free of rounding rules.
 */
class ReportDocumentBuilder
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function basin(array $report): array
    {
        $basin = $report['basin'];
        $geography = $report['geography'];
        $counts = $report['counts'];
        $coverage = $report['coverage'];
        $quality = $report['quality'];

        return $this->document(
            title: 'Basin report — '.$basin['name'],
            meta: [
                ['label' => 'Basin code', 'value' => $basin['code']],
                ['label' => 'Area', 'value' => $this->value($basin['area_km2'], ' km²')],
                ['label' => 'Record status', 'value' => Str::headline($basin['data_status'])],
                ['label' => 'Sub-basins', 'value' => (string) $counts['sub_basins']],
                ['label' => 'Monitoring stations', 'value' => (string) $counts['stations']],
                ['label' => 'Parameters with data', 'value' => (string) $counts['parameters']],
            ],
            sections: [
                [
                    'heading' => 'Specifications',
                    'columns' => [['label' => 'Field'], ['label' => 'Value']],
                    'rows' => [
                        ['Basin code', $basin['code']],
                        ['Name', $basin['name']],
                        ['Alternative name', $this->orDash($basin['name_alt'])],
                        ['Area', $this->value($basin['area_km2'], ' km²')],
                        ['Mean elevation', $this->value($basin['elevation_m'], ' m')],
                        ['Centroid', $this->coordinates($basin['centroid_lat'], $basin['centroid_lng'])],
                        ['Record status', Str::headline($basin['data_status'])],
                        ['Data source', $this->orDash($basin['source'])],
                        ['Source note', $this->orDash($basin['source_note'])],
                        ['Description', $this->orDash($basin['description'])],
                        ['Last updated', $this->orDash($basin['updated_at'])],
                    ],
                ],
                [
                    'heading' => 'Geographic information',
                    'columns' => [['label' => 'Field'], ['label' => 'Value']],
                    'rows' => [
                        ['Boundary stored', $geography['has_boundary'] ? 'Yes' : 'No'],
                        ['Boundary name', $this->orDash($geography['boundary_name'])],
                        ['Coordinate reference system', $geography['boundary_srid'] ? 'EPSG:'.$geography['boundary_srid'] : '—'],
                        ['Boundary vertices', $this->orDash($geography['boundary_vertices'])],
                        ['Bounding box', $this->bounds($geography['bounds'])],
                        ['Centroid', $this->coordinates($geography['centroid'][0] ?? null, $geography['centroid'][1] ?? null)],
                        ['Area', $this->value($geography['area_km2'], ' km²')],
                    ],
                ],
                $this->table(
                    'Sub-basins',
                    ['Code', 'Name', 'Area (km²)', 'Stations', 'Record status'],
                    array_map(fn ($row) => [
                        $row['code'],
                        $row['name'],
                        $this->value($row['area_km2'], ''),
                        (string) $row['stations'],
                        Str::headline($row['data_status']),
                    ], $report['sub_basins']),
                    'No sub-basins have been registered for this basin.'
                ),
                $this->table(
                    'Monitoring stations',
                    ['Code', 'Name', 'Type', 'Sub-basin', 'Parameters', 'Records', 'First record', 'Last record'],
                    array_map(fn ($row) => [
                        $row['code'],
                        $row['name'],
                        Str::headline($row['type']),
                        $this->orDash($row['sub_basin']),
                        (string) $row['parameters'],
                        (string) $row['observations'],
                        $this->orDash($row['first_at']),
                        $this->orDash($row['last_at']),
                    ], $report['stations']),
                    'No monitoring stations are registered in this basin.'
                ),
                $this->table(
                    'Parameters and data coverage',
                    ['Parameter', 'Unit', 'Records', 'Stations', 'First record', 'Last record', 'Days observed', 'Missing'],
                    array_map(fn ($row) => [
                        $row['name'],
                        $this->orDash($row['unit']),
                        (string) $row['records'],
                        (string) $row['stations'],
                        $this->orDash($row['first_at']),
                        $this->orDash($row['last_at']),
                        (string) $row['observed_days'],
                        $this->percent($row['missing_percent']),
                    ], $report['parameters']),
                    'No observations have been loaded for this basin.'
                ),
                [
                    'heading' => 'Data coverage and quality',
                    'columns' => [['label' => 'Measure'], ['label' => 'Value']],
                    'rows' => [
                        ['First observation', $this->orDash($coverage['first_at'])],
                        ['Last observation', $this->orDash($coverage['last_at'])],
                        ['Calendar days covered', (string) $coverage['span_days']],
                        ['Days with records', (string) $coverage['observed_days']],
                        ['Days without records', (string) $coverage['missing_days']],
                        ['Total observations', (string) $quality['records']],
                        ['Approved', (string) $quality['approved']],
                        ['Pending review', (string) $quality['pending']],
                        ['Flagged', (string) $quality['flagged']],
                        ['Rejected', (string) $quality['rejected']],
                        ['Approved share', $this->percent($quality['approved_percent'])],
                        ['Open quality issues', (string) $quality['open_issues']],
                    ],
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function station(array $report): array
    {
        $station = $report['station'];
        $coverage = $report['coverage'];
        $quality = $report['quality'];

        return $this->document(
            title: 'Station report — '.$station['name'],
            meta: [
                ['label' => 'Station code', 'value' => $station['code']],
                ['label' => 'Basin', 'value' => $this->orDash($station['basin'])],
                ['label' => 'Station type', 'value' => Str::headline($station['type'])],
                ['label' => 'Parameters', 'value' => (string) $report['counts']['parameters']],
                ['label' => 'Observations', 'value' => (string) $coverage['records']],
                ['label' => 'Record status', 'value' => Str::headline($station['data_status'])],
            ],
            sections: [
                [
                    'heading' => 'Station details',
                    'columns' => [['label' => 'Field'], ['label' => 'Value']],
                    'rows' => [
                        ['Station code', $station['code']],
                        ['Name', $station['name']],
                        ['Alternative name', $this->orDash($station['name_alt'])],
                        ['Basin', $this->orDash($station['basin'])],
                        ['Sub-basin', $this->orDash($station['sub_basin'])],
                        ['Watershed', $this->orDash($station['watershed'])],
                        ['Coordinates', $this->coordinates($station['latitude'], $station['longitude'])],
                        ['Elevation', $this->value($station['elevation_m'], ' m')],
                        ['Operator', $this->orDash($station['operator'])],
                        ['Established', $this->orDash($station['established_on'])],
                        ['Record status', Str::headline($station['data_status'])],
                        ['Active', $station['is_active'] ? 'Yes' : 'No'],
                        ['Notes', $this->orDash($station['notes'])],
                    ],
                ],
                $this->table(
                    'Available parameters',
                    ['Parameter', 'Unit', 'Records', 'First record', 'Last record', 'Days observed', 'Missing'],
                    array_map(fn ($row) => [
                        $row['name'],
                        $this->orDash($row['unit']),
                        (string) $row['records'],
                        $this->orDash($row['first_at']),
                        $this->orDash($row['last_at']),
                        (string) $row['observed_days'],
                        $this->percent($row['missing_percent']),
                    ], $report['parameters']),
                    'No parameters are registered for this station.'
                ),
                [
                    'heading' => 'Data coverage',
                    'columns' => [['label' => 'Measure'], ['label' => 'Value']],
                    'rows' => [
                        ['First observation', $this->orDash($coverage['first_at'])],
                        ['Last observation', $this->orDash($coverage['last_at'])],
                        ['Calendar days covered', (string) $coverage['span_days']],
                        ['Days with records', (string) $coverage['observed_days']],
                        ['Days without records', (string) $coverage['missing_days']],
                        ['Observations', (string) $coverage['records']],
                    ],
                ],
                [
                    'heading' => 'Quality indicators',
                    'columns' => [['label' => 'Indicator'], ['label' => 'Value']],
                    'rows' => [
                        ['Approved', (string) $quality['approved']],
                        ['Pending review', (string) $quality['pending']],
                        ['Flagged', (string) $quality['flagged']],
                        ['Rejected', (string) $quality['rejected']],
                        ['Approved share', $this->percent($quality['approved_percent'])],
                        ['Open quality issues', (string) $quality['open_issues']],
                    ],
                ],
                $this->table(
                    'Recent observations',
                    ['Measured at', 'Parameter', 'Value', 'Unit', 'Quality', 'Record type'],
                    array_map(fn ($row) => [
                        (string) $row['measured_at'],
                        $this->orDash($row['parameter']),
                        $this->number($row['value']),
                        $this->orDash($row['unit']),
                        Str::headline($row['quality_status']),
                        Str::headline($row['record_type']),
                    ], $report['recent']),
                    'No observations have been loaded for this station.'
                ),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function analysis(array $result, array $filters, string $scope): array
    {
        $profile = ParameterProfile::for(\App\Models\Parameter::with('defaultUnit')->find($filters['parameter_id']));
        $series = $result['series'];
        $summary = $series['summary'];
        $coverage = $result['coverage'];

        $rows = array_map(fn ($point) => [
            $point['label'],
            $this->number($point['value']),
            $this->number($point['min']),
            $this->number($point['max']),
            (string) $point['records'],
        ], $series['points']);

        return $this->document(
            title: 'Hydrological analysis — '.$profile->name(),
            meta: [
                ['label' => 'Parameter', 'value' => $profile->name()],
                ['label' => 'Aggregation', 'value' => $profile->label($profile->primaryMetric())],
                ['label' => 'Unit', 'value' => $this->orDash($profile->unitSymbol())],
                ['label' => 'Scope', 'value' => $scope],
                ['label' => 'Granularity', 'value' => Str::headline($series['granularity'])],
                ['label' => 'Period', 'value' => ($filters['from'] ?? '—').' to '.($filters['to'] ?? '—')],
            ],
            sections: [
                [
                    'heading' => 'Summary statistics',
                    'columns' => [['label' => 'Measure'], ['label' => 'Value']],
                    'rows' => [
                        ['Readings', (string) $summary['records']],
                        ['Aggregation applied', $profile->label($profile->primaryMetric())],
                        ['Accumulating parameter', $profile->accumulates() ? 'Yes — summed' : 'No — averaged'],
                        ['Minimum', $this->number($summary['minimum'])],
                        ['Maximum', $this->number($summary['maximum'])],
                        ['Average', $this->number($summary['mean'])],
                        ['Total', $profile->accumulates() ? $this->number($summary['total']) : 'Not applicable'],
                        ['First observation', $this->orDash($coverage['first_at'])],
                        ['Last observation', $this->orDash($coverage['last_at'])],
                        ['Days with records', (string) $coverage['observed_days']],
                        ['Days without records', (string) $coverage['missing_days']],
                        ['Approved share', $this->percent($coverage['approved_percent'])],
                    ],
                ],
                $this->table(
                    Str::headline($series['granularity']).' values',
                    ['Period', $profile->chartLabel($series['metric']).' ('.$this->orDash($profile->unitSymbol()).')', 'Minimum', 'Maximum', 'Readings'],
                    $rows,
                    'No approved observations match the selected filters.'
                ),
            ],
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $meta
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function document(string $title, array $meta, array $sections): array
    {
        return [
            'title' => $title,
            'subtitle' => 'EEC Basin & Hydrological Data Management System',
            'generated_at' => now()->format('d M Y H:i'),
            'footer' => 'EEC-BHDMS · '.$title,
            'meta' => $meta,
            'sections' => $sections,
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<int, string>>  $rows
     * @return array<string, mixed>
     */
    private function table(string $heading, array $columns, array $rows, string $empty): array
    {
        return [
            'heading' => $heading,
            'columns' => array_map(fn (string $label) => ['label' => $label], $columns),
            'rows' => $rows,
            'empty' => $empty,
        ];
    }

    private function value(mixed $value, string $suffix): string
    {
        return $value === null ? '—' : $this->number($value).$suffix;
    }

    private function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 3, '.', ',');
    }

    private function percent(mixed $value): string
    {
        return $value === null ? '—' : $value.'%';
    }

    private function orDash(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }

    private function coordinates(mixed $lat, mixed $lng): string
    {
        if ($lat === null || $lng === null) {
            return '—';
        }

        return number_format((float) $lat, 5).', '.number_format((float) $lng, 5);
    }

    /**
     * @param  array<string, float>|null  $bounds
     */
    private function bounds(?array $bounds): string
    {
        if (! $bounds) {
            return '—';
        }

        return sprintf(
            'W %.4f · S %.4f · E %.4f · N %.4f',
            $bounds['west'],
            $bounds['south'],
            $bounds['east'],
            $bounds['north'],
        );
    }
}
