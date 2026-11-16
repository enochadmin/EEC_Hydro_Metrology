import Badge from '@/Components/Badge';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import StatCard from '@/Components/StatCard';
import AppLayout from '@/Layouts/AppLayout';
import { formatArea, formatNumber } from '@/Utils/format';
import { stationTypeLabel } from '@/Utils/basins';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, Database, RadioTower, Waves } from 'lucide-react';
import { useMemo, useState } from 'react';

const STATUS_TONES = { verified: 'success', provisional: 'warning', pending: 'neutral' };

export default function ReportsIndex({ overview, abilities = {} }) {
    const [stationQuery, setStationQuery] = useState('');

    const stations = useMemo(() => {
        const term = stationQuery.trim().toLowerCase();

        if (!term) {
            return overview.stations;
        }

        return overview.stations.filter(
            (station) =>
                station.name.toLowerCase().includes(term) ||
                station.code.toLowerCase().includes(term) ||
                (station.basin ?? '').toLowerCase().includes(term),
        );
    }, [overview.stations, stationQuery]);

    const span = overview.span;

    return (
        <AppLayout breadcrumbs={[{ label: 'Reports' }]}>
            <Head title="Reports" />

            <PageHeader
                title="Reports"
                description="Basin and station reports, hydrological summaries and exports."
                actions={
                    <Link
                        href="/reports/analysis"
                        className="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white transition-colors hover:bg-brand-800 focus-ring dark:bg-brand-600 dark:hover:bg-brand-500"
                    >
                        <BarChart3 className="h-3.5 w-3.5" strokeWidth={2} />
                        Hydrological analysis
                    </Link>
                }
            />

            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Basins" value={overview.totals.basins} icon={Waves} />
                <StatCard label="Stations" value={overview.totals.stations} icon={RadioTower} />
                <StatCard label="Observations" value={overview.totals.observations} icon={Database} />
                <StatCard
                    label="Approved records"
                    value={overview.totals.approved}
                    icon={BarChart3}
                    hint={span.first_at ? `${span.first_at} to ${span.last_at}` : 'No observations stored yet'}
                />
            </div>

            <Card className="mt-4">
                <CardHeader icon={Waves} title="Basin reports" description="Specifications, geography, stations and data coverage." />

                <div className="overflow-x-auto">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                <th className="px-5 py-2.5">Basin</th>
                                <th className="px-5 py-2.5">Code</th>
                                <th className="px-5 py-2.5 text-right">Area</th>
                                <th className="px-5 py-2.5 text-right">Sub-basins</th>
                                <th className="px-5 py-2.5 text-right">Stations</th>
                                <th className="px-5 py-2.5">Status</th>
                                <th className="px-5 py-2.5" />
                            </tr>
                        </thead>
                        <tbody>
                            {overview.basins.map((basin) => (
                                <tr key={basin.id} className="border-b border-line/70 last:border-0 hover:bg-canvas/60">
                                    <td className="px-5 py-2.5 text-[12.5px] font-medium text-ink">{basin.name}</td>
                                    <td className="px-5 py-2.5 text-[12px] text-muted">{basin.code}</td>
                                    <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatArea(basin.area_km2)}</td>
                                    <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatNumber(basin.sub_basins)}</td>
                                    <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatNumber(basin.stations)}</td>
                                    <td className="px-5 py-2.5">
                                        <Badge size="sm" tone={STATUS_TONES[basin.data_status] ?? 'neutral'}>
                                            {basin.data_status}
                                        </Badge>
                                    </td>
                                    <td className="px-5 py-2.5 text-right">
                                        <Link
                                            href={`/reports/basins/${basin.id}`}
                                            className="inline-flex items-center gap-1 text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                                        >
                                            Open <ArrowRight className="h-3 w-3" strokeWidth={2} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card className="mt-4">
                <CardHeader
                    icon={RadioTower}
                    title="Station reports"
                    description="Details, parameters, coverage, missing data and quality indicators."
                    action={
                        <input
                            type="search"
                            value={stationQuery}
                            onChange={(event) => setStationQuery(event.target.value)}
                            placeholder="Search station or basin"
                            className="w-56 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[11.5px] text-ink placeholder:text-muted/70 focus-ring"
                        />
                    }
                />

                {stations.length === 0 ? (
                    <CardBody>
                        <p className="py-6 text-center text-[12px] text-muted">
                            {overview.stations.length === 0
                                ? 'No monitoring stations are registered yet, so there is nothing to report on.'
                                : 'No station matches that search.'}
                        </p>
                    </CardBody>
                ) : (
                    <div className="eec-scrollbar max-h-[420px] overflow-auto">
                        <table className="w-full text-left">
                            <thead className="sticky top-0 bg-surface">
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">Station</th>
                                    <th className="px-5 py-2.5">Code</th>
                                    <th className="px-5 py-2.5">Type</th>
                                    <th className="px-5 py-2.5">Basin</th>
                                    <th className="px-5 py-2.5" />
                                </tr>
                            </thead>
                            <tbody>
                                {stations.map((station) => (
                                    <tr key={station.id} className="border-b border-line/70 last:border-0 hover:bg-canvas/60">
                                        <td className="px-5 py-2.5 text-[12.5px] font-medium text-ink">{station.name}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{station.code}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{stationTypeLabel(station.type)}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{station.basin ?? '—'}</td>
                                        <td className="px-5 py-2.5 text-right">
                                            <Link
                                                href={`/reports/stations/${station.id}`}
                                                className="inline-flex items-center gap-1 text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                                            >
                                                Open <ArrowRight className="h-3 w-3" strokeWidth={2} />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {!abilities.export && (
                <p className="mt-4 text-[11px] text-muted">
                    You can read every report. Downloading files requires the export reports permission.
                </p>
            )}
        </AppLayout>
    );
}
