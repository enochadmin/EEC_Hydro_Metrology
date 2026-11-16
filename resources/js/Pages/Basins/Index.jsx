import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Can from '@/Components/Can';
import { Card } from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { useAuthorization } from '@/Hooks/useAuthorization';
import { boundaryStatusMeta, dataStatusLabel, dataStatusTone } from '@/Utils/basins';
import { cn } from '@/Utils/cn';
import { formatNumber } from '@/Utils/format';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Download, Eye, Pencil, Plus, Search, Trash2, Waves, X } from 'lucide-react';
import { useState } from 'react';

const filterClasses =
    'h-9 rounded-lg border-line bg-surface py-0 pl-3 pr-8 text-[13px] text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500';

function SortHeader({ column, label, filters, onSort, className }) {
    const active = filters.sort === column;
    const Icon = !active ? ArrowUpDown : filters.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <th
            scope="col"
            aria-sort={active ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none'}
            className={cn('px-4 py-2.5 font-semibold', className)}
        >
            <button
                type="button"
                onClick={() => onSort(column)}
                className={cn(
                    'inline-flex items-center gap-1 rounded transition-colors hover:text-ink focus-ring',
                    active ? 'text-ink' : 'text-muted',
                )}
            >
                {label}
                <Icon className={cn('h-3 w-3', active ? 'text-brand-600' : 'text-muted/60')} strokeWidth={2} />
            </button>
        </th>
    );
}

export default function Index({ basins, filters = {}, summary = {}, statuses = [] }) {
    const { can } = useAuthorization();
    const { errors = {} } = usePage().props;

    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingDelete, setPendingDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const apply = (next = {}) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, ...next }).filter(([, value]) => value !== '' && value !== null && value !== undefined),
        );

        router.get('/basins', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const sortBy = (column) => {
        const direction = filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
        apply({ sort: column, direction });
    };

    const resetFilters = () => {
        setSearch('');
        router.get('/basins');
    };

    const exportUrl = `/basins/export?${new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([key, value]) => key !== 'sort' && key !== 'direction' && value !== '')),
    ).toString()}`;

    const confirmDelete = () => {
        if (!pendingDelete) {
            return;
        }

        router.delete(`/basins/${pendingDelete.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(null);
            },
        });
    };

    const hasFilters = Boolean(filters.search || filters.status || filters.state || filters.boundary);

    return (
        <AppLayout breadcrumbs={[{ label: 'Basin registry' }, { label: 'Basins' }]}>
            <Head title="Basins" />

            <PageHeader
                title="Basins"
                description="The twelve major Ethiopian river basins."
                actions={
                    <>
                        <Button variant="secondary" size="sm" onClick={() => window.location.assign(exportUrl)}>
                            <Download className="h-4 w-4" strokeWidth={1.75} />
                            Export CSV
                        </Button>
                        <Can permission="create basins">
                            <Button href="/basins/create" size="sm">
                                <Plus className="h-4 w-4" strokeWidth={2} />
                                New basin
                            </Button>
                        </Can>
                    </>
                }
            />

            <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    { label: 'Registered basins', value: summary.total ?? 0, tone: 'text-ink', hint: 'Active registry records' },
                    { label: 'Boundaries mapped', value: summary.boundary_mapped ?? 0, tone: 'text-emerald-600 dark:text-emerald-400', hint: 'Primary polygon stored' },
                    { label: 'Awaiting boundary', value: summary.boundary_pending ?? 0, tone: 'text-amber-600 dark:text-amber-400', hint: 'No polygon imported yet' },
                    { label: 'Sub-basins', value: summary.sub_basins ?? 0, tone: 'text-ink', hint: `${formatNumber(summary.stations ?? 0)} monitoring stations` },
                ].map((item) => (
                    <Card key={item.label} className="px-5 py-4">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{item.label}</p>
                        <p className={cn('mt-2 text-2xl font-semibold tabular', item.tone)}>{formatNumber(item.value)}</p>
                        <p className="mt-1 text-[11.5px] text-muted">{item.hint}</p>
                    </Card>
                ))}
            </div>

            {errors.basin && (
                <div className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                    {errors.basin}
                </div>
            )}

            <Card className="mt-4 overflow-hidden">
                <div className="flex flex-col gap-3 border-b border-line px-5 py-3.5 xl:flex-row xl:items-center xl:justify-between">
                    <form onSubmit={(event) => { event.preventDefault(); apply({ search }); }} className="relative w-full xl:max-w-sm">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" strokeWidth={1.75} />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search code, name or alternative name"
                            aria-label="Search basins"
                            className="h-9 w-full rounded-lg border-line bg-surface pl-9 pr-3 text-[13px] text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500"
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <label className="sr-only" htmlFor="basin-status-filter">Data status</label>
                        <select id="basin-status-filter" value={filters.status ?? ''} onChange={(event) => apply({ status: event.target.value })} className={filterClasses}>
                            <option value="">All data statuses</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>{status.label}</option>
                            ))}
                        </select>

                        <label className="sr-only" htmlFor="basin-boundary-filter">Boundary status</label>
                        <select id="basin-boundary-filter" value={filters.boundary ?? ''} onChange={(event) => apply({ boundary: event.target.value })} className={filterClasses}>
                            <option value="">All boundary states</option>
                            <option value="mapped">Mapped</option>
                            <option value="pending">Pending</option>
                        </select>

                        <label className="sr-only" htmlFor="basin-state-filter">Record status</label>
                        <select id="basin-state-filter" value={filters.state ?? ''} onChange={(event) => apply({ state: event.target.value })} className={filterClasses}>
                            <option value="">All records</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={resetFilters}>
                                <X className="h-3.5 w-3.5" strokeWidth={2} />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {basins.data.length === 0 ? (
                    <EmptyState
                        icon={Waves}
                        title="No basins match this view"
                        description="Adjust the search or clear the filters."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                <tr>
                                    <SortHeader column="code" label="Basin code" filters={filters} onSort={sortBy} className="pl-5" />
                                    <SortHeader column="name" label="Basin name" filters={filters} onSort={sortBy} />
                                    <SortHeader column="area_km2" label="Area (km²)" filters={filters} onSort={sortBy} className="text-right" />
                                    <SortHeader column="sub_basins_count" label="Sub-basins" filters={filters} onSort={sortBy} className="text-right" />
                                    <SortHeader column="stations_count" label="Stations" filters={filters} onSort={sortBy} className="text-right" />
                                    <th scope="col" className="px-4 py-2.5 font-semibold">Boundary</th>
                                    <th scope="col" className="px-4 py-2.5 font-semibold">Record status</th>
                                    <th scope="col" className="px-4 py-2.5 pr-5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {basins.data.map((basin) => {
                                    const boundary = boundaryStatusMeta(basin.boundary_status);

                                    return (
                                        <tr key={basin.id} className="transition-colors hover:bg-canvas/50">
                                            <td className="whitespace-nowrap px-4 py-3 pl-5">
                                                <span className="rounded bg-canvas px-1.5 py-0.5 font-mono text-[12px] font-medium text-ink">{basin.code}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-[13px] font-medium text-ink">{basin.name}</p>
                                                    <p className="truncate text-[11.5px] text-muted">
                                                        {basin.name_alt || basin.data_source?.name || 'No alternative name recorded'}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right text-[12.5px] tabular text-ink">
                                                {basin.area_km2 === null ? <span className="text-muted">—</span> : formatNumber(basin.area_km2, { maximumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-4 py-3 text-right text-[12.5px] tabular text-muted">{formatNumber(basin.sub_basins_count)}</td>
                                            <td className="px-4 py-3 text-right text-[12.5px] tabular text-muted">{formatNumber(basin.stations_count)}</td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <Badge tone={boundary.tone} size="sm" title={`${boundary.description} ${basin.boundary_versions} version(s) on file.`}>
                                                    {boundary.short}
                                                </Badge>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-1">
                                                    <Badge tone={dataStatusTone(basin.data_status)} size="sm">{dataStatusLabel(basin.data_status)}</Badge>
                                                    {!basin.is_active && <Badge tone="danger" size="sm">Inactive</Badge>}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 pr-5">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button href={`/basins/${basin.id}`} variant="ghost" size="icon" aria-label={`View ${basin.name}`}>
                                                        <Eye className="h-4 w-4" strokeWidth={1.75} />
                                                    </Button>
                                                    {can('edit basins') && (
                                                        <Button href={`/basins/${basin.id}/edit`} variant="ghost" size="icon" aria-label={`Edit ${basin.name}`}>
                                                            <Pencil className="h-4 w-4" strokeWidth={1.75} />
                                                        </Button>
                                                    )}
                                                    {can('delete basins') && (
                                                        <Button
                                                            variant="danger-ghost"
                                                            size="icon"
                                                            aria-label={`Delete ${basin.name}`}
                                                            onClick={() => setPendingDelete(basin)}
                                                        >
                                                            <Trash2 className="h-4 w-4" strokeWidth={1.75} />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={basins} />
            </Card>

            <ConfirmDialog
                open={Boolean(pendingDelete)}
                title="Delete this basin?"
                description={
                    pendingDelete
                        ? `${pendingDelete.code} — ${pendingDelete.name} will be archived (recoverable). A basin that still has sub-basins or monitoring stations cannot be deleted.`
                        : undefined
                }
                confirmLabel="Delete basin"
                processing={deleting}
                onConfirm={confirmDelete}
                onClose={() => setPendingDelete(null)}
            />
        </AppLayout>
    );
}
