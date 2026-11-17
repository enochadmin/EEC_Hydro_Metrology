import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card } from '@/Components/Card';
import Can from '@/Components/Can';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { useAuthorization } from '@/Hooks/useAuthorization';
import { formatDate, formatNumber } from '@/Utils/format';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpDown, MapPin, Plus, RadioTower, Search, X } from 'lucide-react';
import { useState } from 'react';

const filterClasses = 'h-9 rounded-lg border-line bg-surface py-1.5 pl-3 pr-8 text-xs text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500';
const cellHead = 'px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-[0.1em] text-muted';

function SummaryCard({ label, value, hint }) {
    return (
        <Card className="px-4 py-3.5">
            <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className="mt-1.5 text-2xl font-semibold tabular text-ink">{formatNumber(value ?? 0)}</p>
            <p className="mt-0.5 text-[11px] text-muted">{hint}</p>
        </Card>
    );
}

export default function Index({ stations, filters, categories = [], basins = [], subBasins = [], summary = {} }) {
    const { can } = useAuthorization();
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (updates = {}) => {
        router.get('/stations', { ...filters, ...updates, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const sortBy = (column) => {
        const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        apply({ sort: column, direction });
    };

    const hasFilters = Boolean(filters.search || filters.station_type || filters.basin_id || filters.sub_basin_id || filters.state || filters.availability);
    const clearFilters = () => {
        setSearch('');
        router.get('/stations');
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Hydrological data' }, { label: 'Monitoring stations' }]}>
            <Head title="Monitoring stations" />
            <PageHeader
                title="Monitoring stations"
                description="Station registry, assigned parameters, operating status, and observed data coverage."
                actions={
                    <Can permission="create stations">
                        <Button href="/stations/create" size="sm"><Plus className="h-4 w-4" />Register station</Button>
                    </Can>
                }
            />

            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryCard label="Registered stations" value={summary.total} hint="Active and archived history" />
                <SummaryCard label="Operational" value={summary.active} hint="Currently marked active" />
                <SummaryCard label="Mapped" value={summary.mapped} hint="Have confirmed coordinates" />
                <SummaryCard label="With observations" value={summary.with_data} hint="At least one recorded value" />
            </div>

            <Card className="mt-4 overflow-hidden">
                <div className="flex flex-col gap-3 border-b border-line px-4 py-3.5 xl:flex-row xl:items-center xl:justify-between">
                    <form onSubmit={(event) => { event.preventDefault(); apply({ search }); }} className="relative w-full xl:max-w-sm">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" strokeWidth={1.75} />
                        <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search code, station, or organization" aria-label="Search monitoring stations" className="h-9 w-full rounded-lg border-line bg-surface pl-9 pr-3 text-[13px] text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500" />
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <label className="sr-only" htmlFor="station-type-filter">Station type</label>
                        <select id="station-type-filter" value={filters.station_type ?? ''} onChange={(event) => apply({ station_type: event.target.value })} className={filterClasses}>
                            <option value="">All station types</option>
                            {categories.map((category) => <option key={category.code} value={category.code}>{category.label}</option>)}
                        </select>
                        <label className="sr-only" htmlFor="station-basin-filter">Basin</label>
                        <select id="station-basin-filter" value={filters.basin_id ?? ''} onChange={(event) => apply({ basin_id: event.target.value, sub_basin_id: '' })} className={filterClasses}>
                            <option value="">All basins</option>
                            {basins.map((basin) => <option key={basin.id} value={basin.id}>{basin.code} — {basin.name}</option>)}
                        </select>
                        <label className="sr-only" htmlFor="station-sub-basin-filter">Sub-basin</label>
                        <select id="station-sub-basin-filter" value={filters.sub_basin_id ?? ''} onChange={(event) => apply({ sub_basin_id: event.target.value })} className={filterClasses}>
                            <option value="">All sub-basins</option>
                            {subBasins.filter((subBasin) => !filters.basin_id || String(subBasin.basin_id) === String(filters.basin_id)).map((subBasin) => <option key={subBasin.id} value={subBasin.id}>{subBasin.code} — {subBasin.name}</option>)}
                        </select>
                        <label className="sr-only" htmlFor="station-state-filter">Operational status</label>
                        <select id="station-state-filter" value={filters.state ?? ''} onChange={(event) => apply({ state: event.target.value })} className={filterClasses}>
                            <option value="">All statuses</option>
                            <option value="active">Operational</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <label className="sr-only" htmlFor="station-data-filter">Data availability</label>
                        <select id="station-data-filter" value={filters.availability ?? ''} onChange={(event) => apply({ availability: event.target.value })} className={filterClasses}>
                            <option value="">All data states</option>
                            <option value="with_data">Has observations</option>
                            <option value="without_data">No observations</option>
                        </select>
                        {hasFilters && <Button variant="ghost" size="sm" onClick={clearFilters}><X className="h-3.5 w-3.5" />Clear</Button>}
                    </div>
                </div>

                {stations.data.length === 0 ? (
                    <EmptyState
                        icon={RadioTower}
                        title={hasFilters ? 'No stations match this view' : 'No monitoring stations registered'}
                        description={hasFilters ? 'Adjust the search or clear the filters.' : 'Register a station to begin managing its location, parameters, and observed data.'}
                        action={!hasFilters && can('create stations') ? <Button href="/stations/create" size="sm"><Plus className="h-4 w-4" />Register station</Button> : null}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-[980px] w-full text-sm">
                            <thead className="border-b border-line bg-canvas/60">
                                <tr>
                                    <th className={cellHead}><button onClick={() => sortBy('code')} className="inline-flex items-center gap-1">Station <ArrowUpDown className="h-3 w-3" /></button></th>
                                    <th className={cellHead}><button onClick={() => sortBy('station_type')} className="inline-flex items-center gap-1">Type <ArrowUpDown className="h-3 w-3" /></button></th>
                                    <th className={cellHead}>Basin / sub-basin</th>
                                    <th className={cellHead}>Parameters</th>
                                    <th className={cellHead}>Status</th>
                                    <th className={cellHead}><button onClick={() => sortBy('observations_count')} className="inline-flex items-center gap-1">Data availability <ArrowUpDown className="h-3 w-3" /></button></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {stations.data.map((station) => (
                                    <tr key={station.id} className="transition-colors hover:bg-canvas/50">
                                        <td className="px-4 py-3">
                                            <Link href={'/stations/' + station.id} className="group flex min-w-0 items-start gap-2.5">
                                                <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200"><RadioTower className="h-4 w-4" /></span>
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium text-ink group-hover:text-brand-700 dark:group-hover:text-brand-200">{station.name}</span>
                                                    <span className="mt-0.5 block font-mono text-[10.5px] text-muted">{station.code}{station.name_alt ? ' · ' + station.name_alt : ''}</span>
                                                </span>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3"><Badge tone="brand" size="sm">{station.category?.label ?? station.station_type}</Badge></td>
                                        <td className="px-4 py-3 text-[12px] text-ink">
                                            <span className="block">{station.basin?.code ?? '—'} · {station.basin?.name ?? 'No basin'}</span>
                                            <span className="mt-0.5 block text-[11px] text-muted">{station.sub_basin?.name ?? 'No sub-basin'}</span>
                                        </td>
                                        <td className="max-w-[230px] px-4 py-3">
                                            {station.parameters.length === 0 ? <span className="text-xs text-muted">None assigned</span> : (
                                                <div className="flex flex-wrap gap-1">
                                                    {station.parameters.slice(0, 3).map((parameter) => <Badge key={parameter.id} tone="neutral" size="sm">{parameter.code}{parameter.unit ? ' · ' + parameter.unit : ''}</Badge>)}
                                                    {station.parameters.length > 3 && <span className="text-[10px] text-muted">+{station.parameters.length - 3}</span>}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={station.is_active ? 'success' : 'danger'} size="sm">{station.is_active ? 'Operational' : 'Inactive'}</Badge>
                                        </td>
                                        <td className="px-4 py-3 text-[11.5px] text-ink">
                                            <span className="block font-medium">{formatNumber(station.observations_count)} records</span>
                                            <span className="mt-0.5 block text-muted">
                                                {station.first_observation ? formatDate(station.first_observation) + ' – ' + formatDate(station.last_observation) : 'No observations'}
                                            </span>
                                            {station.latitude !== null && <span className="mt-0.5 flex items-center gap-1 text-[10.5px] text-muted"><MapPin className="h-3 w-3" />Mapped</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination meta={stations} />
            </Card>
        </AppLayout>
    );
}
