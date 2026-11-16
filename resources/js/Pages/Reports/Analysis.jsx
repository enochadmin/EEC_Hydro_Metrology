import { Card, CardBody } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import ChartCard from '@/Components/reports/ChartCard';
import ComparisonChart from '@/Components/reports/ComparisonChart';
import CoverageTiles from '@/Components/reports/CoverageTiles';
import DistributionChart from '@/Components/reports/DistributionChart';
import ExportMenu from '@/Components/reports/ExportMenu';
import MonthlyProfileChart from '@/Components/reports/MonthlyProfileChart';
import TimeSeriesChart from '@/Components/reports/TimeSeriesChart';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/Utils/format';
import { GRANULARITY_LABELS, formatMetric, formatPercent } from '@/Utils/reports';
import { Head, router } from '@inertiajs/react';
import { BarChart3, CalendarRange, Layers, LineChart, PieChart, RadioTower, Waves } from 'lucide-react';
import { useMemo, useState } from 'react';

const inputClass =
    'w-full rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[12px] text-ink focus-ring disabled:opacity-60';
const labelClass = 'mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted';

export default function Analysis({ filters, options, result, abilities = {} }) {
    const [form, setForm] = useState({
        parameter_id: String(filters.parameter_id ?? ''),
        basin_id: filters.basin_id ? String(filters.basin_id) : '',
        station_id: filters.station_id ? String(filters.station_id) : '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        granularity: filters.granularity ?? 'monthly',
        quality: filters.quality ?? 'approved',
    });

    const selectedParameter = options.parameters.find((parameter) => String(parameter.id) === form.parameter_id);
    const profile = result.series.profile;
    const summary = result.series.summary;

    const stations = useMemo(() => {
        if (!form.basin_id) {
            return options.stations;
        }

        return options.stations.filter((station) => String(station.basin_id) === form.basin_id);
    }, [options.stations, form.basin_id]);

    function update(key, value) {
        setForm((current) => ({
            ...current,
            [key]: value,
            ...(key === 'basin_id' ? { station_id: '' } : {}),
        }));
    }

    function submit(event) {
        event.preventDefault();

        router.get('/reports/analysis', form, { preserveState: true, preserveScroll: true });
    }

    const query = new URLSearchParams(
        Object.entries(form).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    ).toString();

    const hasSeries = result.series.points.length > 0;
    const hasSeasonal = result.seasonal.points.length > 0;
    const hasDistribution = result.distribution.bins.length > 0;

    const tiles = [
        { label: 'Readings', value: formatNumber(summary.records) },
        { label: 'Minimum', value: formatMetric(summary.minimum, { unit: profile.unit, decimals: profile.decimals }) },
        { label: 'Maximum', value: formatMetric(summary.maximum, { unit: profile.unit, decimals: profile.decimals }) },
        { label: 'Average', value: formatMetric(summary.mean, { unit: profile.unit, decimals: profile.decimals }) },
    ];

    if (profile.accumulates) {
        tiles.push({ label: 'Total', value: formatMetric(summary.total, { unit: profile.unit, decimals: profile.decimals }) });
        tiles.push({ label: 'Approved share', value: formatPercent(result.coverage.approved_percent) });
    } else {
        tiles.push({ label: 'Days without records', value: formatNumber(result.coverage.missing_days) });
        tiles.push({ label: 'Approved share', value: formatPercent(result.coverage.approved_percent) });
    }

    return (
        <AppLayout breadcrumbs={[{ label: 'Reports', href: '/reports' }, { label: 'Hydrological analysis' }]}>
            <Head title="Hydrological analysis" />

            <PageHeader
                title="Hydrological analysis"
                description={`${selectedParameter?.name ?? 'Parameter'} · ${result.scope}`}
                actions={
                    <ExportMenu
                        baseUrl={`/reports/analysis/export`}
                        formats={['csv', 'xlsx', 'pdf']}
                        query={query}
                        disabled={!abilities.export || (form.quality === 'all' && !abilities.export_restricted)}
                        disabledNote={
                            form.quality === 'all'
                                ? 'Exporting provisional records requires the export restricted reports permission.'
                                : undefined
                        }
                    />
                }
            />

            <Card className="mt-6">
                <CardBody>
                    <form onSubmit={submit} className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label className={labelClass} htmlFor="parameter">Parameter</label>
                            <select
                                id="parameter"
                                className={inputClass}
                                value={form.parameter_id}
                                onChange={(event) => update('parameter_id', event.target.value)}
                            >
                                {options.parameters.map((parameter) => (
                                    <option key={parameter.id} value={parameter.id}>
                                        {parameter.name} ({parameter.primary_label.toLowerCase()})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="basin">Basin</label>
                            <select
                                id="basin"
                                className={inputClass}
                                value={form.basin_id}
                                onChange={(event) => update('basin_id', event.target.value)}
                            >
                                <option value="">All basins</option>
                                {options.basins.map((basin) => (
                                    <option key={basin.id} value={basin.id}>{basin.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="station">Station</label>
                            <select
                                id="station"
                                className={inputClass}
                                value={form.station_id}
                                onChange={(event) => update('station_id', event.target.value)}
                                disabled={stations.length === 0}
                            >
                                <option value="">All stations</option>
                                {stations.map((station) => (
                                    <option key={station.id} value={station.id}>
                                        {station.name} ({station.code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="granularity">Aggregation period</label>
                            <select
                                id="granularity"
                                className={inputClass}
                                value={form.granularity}
                                onChange={(event) => update('granularity', event.target.value)}
                            >
                                {Object.entries(GRANULARITY_LABELS).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="from">From</label>
                            <input
                                id="from"
                                type="date"
                                className={inputClass}
                                value={form.from}
                                onChange={(event) => update('from', event.target.value)}
                            />
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="to">To</label>
                            <input
                                id="to"
                                type="date"
                                className={inputClass}
                                value={form.to}
                                onChange={(event) => update('to', event.target.value)}
                            />
                        </div>

                        <div>
                            <label className={labelClass} htmlFor="quality">Quality filter</label>
                            <select
                                id="quality"
                                className={inputClass}
                                value={form.quality}
                                onChange={(event) => update('quality', event.target.value)}
                                disabled={!abilities.generate}
                            >
                                <option value="approved">Approved records only</option>
                                <option value="all">All records, including provisional</option>
                            </select>
                        </div>

                        <div className="flex items-end gap-2">
                            <button
                                type="submit"
                                className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white transition-colors hover:bg-brand-800 focus-ring dark:bg-brand-600 dark:hover:bg-brand-500"
                            >
                                <CalendarRange className="h-3.5 w-3.5" strokeWidth={2} />
                                Apply filters
                            </button>
                        </div>
                    </form>

                    {form.quality === 'all' && (
                        <p className="mt-2 text-[10.5px] text-amber-700 dark:text-amber-400">
                            Provisional, flagged and rejected readings are included. Exports of this view are restricted.
                        </p>
                    )}
                </CardBody>
            </Card>

            <CoverageTiles className="mt-4" items={tiles} />

            <div className="mt-4">
                <ChartCard
                    icon={LineChart}
                    title={`${GRANULARITY_LABELS[result.series.granularity]} series`}
                    description={`${profile.name} · ${profile.primary_label}${profile.unit ? ` (${profile.unit})` : ''}. Dashed lines mark the minimum and maximum of each period.`}
                    empty={!hasSeries}
                    emptyMessage="No approved observations match these filters."
                >
                    <TimeSeriesChart points={result.series.points} unit={profile.unit} decimals={profile.decimals} height={300} />
                </ChartCard>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <ChartCard
                    icon={BarChart3}
                    title="Monthly profile"
                    description={`${profile.primary_label} by calendar month, pooled across the selected years.`}
                    empty={!hasSeasonal}
                    emptyMessage="Not enough observations to build a monthly profile."
                >
                    <MonthlyProfileChart points={result.seasonal.points} unit={profile.unit} decimals={profile.decimals} />
                </ChartCard>

                <ChartCard
                    icon={PieChart}
                    title="Parameter distribution"
                    description={`${formatNumber(result.distribution.records)} readings from ${formatMetric(result.distribution.minimum, { unit: profile.unit })} to ${formatMetric(result.distribution.maximum, { unit: profile.unit })}.`}
                    empty={!hasDistribution}
                    emptyMessage="No readings to distribute."
                >
                    <DistributionChart bins={result.distribution.bins} unit={profile.unit} decimals={profile.decimals} />
                </ChartCard>

                <ChartCard
                    icon={RadioTower}
                    title="Station comparison"
                    description={`Up to 15 stations, ranked by record count. ${profile.primary_label} per station.`}
                    empty={result.stations.length === 0}
                    emptyMessage="No stations hold this parameter for the selected filters."
                >
                    <ComparisonChart items={result.stations} unit={profile.unit} decimals={profile.decimals} />
                </ChartCard>

                <ChartCard
                    icon={Layers}
                    title="Basin comparison"
                    description={`${profile.primary_label} aggregated per basin for the same filters.`}
                    empty={result.basins.length === 0}
                    emptyMessage="No basin holds this parameter for the selected filters."
                >
                    <ComparisonChart items={result.basins} unit={profile.unit} decimals={profile.decimals} />
                </ChartCard>
            </div>

            <p className="mt-4 inline-flex items-center gap-1.5 text-[11px] text-muted">
                <Waves className="h-3 w-3" strokeWidth={2} />
                {profile.accumulates
                    ? 'This parameter is an accumulation, so periods are summed; the average is shown for reference.'
                    : 'This parameter is a state, so periods are averaged; totals are not reported because they have no physical meaning.'}
                {result.series.truncated && ' The series was truncated to the configured point limit.'}
            </p>

            <p className="mt-1 text-[10.5px] text-muted">
                Computed {result.generated_at}. Exports use this exact filter set.
            </p>
        </AppLayout>
    );
}
