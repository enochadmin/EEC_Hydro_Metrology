import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardHeader } from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Tabs from '@/Components/Tabs';
import BoundaryPanel from '@/Components/geography/BoundaryPanel';
import DocumentsPanel from '@/Components/geography/DocumentsPanel';
import { useAuthorization } from '@/Hooks/useAuthorization';
import AppLayout from '@/Layouts/AppLayout';
import { activityLabel, boundaryStatusMeta, dataStatusLabel, dataStatusTone, stationTypeLabel } from '@/Utils/basins';
import { cn } from '@/Utils/cn';
import { formatArea, formatCoordinate, formatDateTime, formatElevation, formatNumber } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Database, FileText, GitBranch, Globe2, MapPin, Pencil, Plus, RadioTower, Trash2, Waves } from 'lucide-react';
import { useState } from 'react';

const cellHead = 'px-4 py-2.5 font-semibold';

function DetailItem({ label, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</dt>
            <dd className="mt-1 text-[13px] leading-relaxed text-ink">{children}</dd>
        </div>
    );
}

function SummaryTile({ label, value, hint }) {
    return (
        <Card className="px-4 py-3.5">
            <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className="mt-1.5 text-xl font-semibold tabular text-ink">{formatNumber(value)}</p>
            {hint && <p className="mt-0.5 text-[11px] text-muted">{hint}</p>}
        </Card>
    );
}

function QualityBadge({ status }) {
    const tone = { approved: 'success', flagged: 'warning', rejected: 'danger' }[status] ?? 'neutral';

    return <Badge tone={tone} size="sm">{status}</Badge>;
}

export default function Show({ basin, subBasins = [], stations, observations = [], boundaries = [], displayBoundary, documents = [], activity = [], summary = {}, abilities = {} }) {
    const { can } = useAuthorization();
    const [tab, setTab] = useState('overview');
    const [pendingDelete, setPendingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [pendingBoundary, setPendingBoundary] = useState(null);
    const [deletingBoundary, setDeletingBoundary] = useState(false);

    const boundary = boundaryStatusMeta(basin.boundary_status);

    const tabs = [
        { key: 'overview', label: 'Overview' },
        { key: 'sub-basins', label: 'Sub-basins', count: summary.sub_basins ?? 0 },
        { key: 'stations', label: 'Monitoring stations', count: summary.stations ?? 0 },
        { key: 'hydrology', label: 'Hydrological data', count: summary.observations ?? 0 },
        { key: 'geographic', label: 'Geographic information', count: summary.boundaries ?? 0 },
        { key: 'documents', label: 'Documents', count: summary.documents ?? 0 },
        { key: 'activity', label: 'Activity history' },
    ];

    const confirmDelete = () => {
        router.delete(`/basins/${basin.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(false);
            },
        });
    };

    const confirmBoundaryDelete = () => {
        if (!pendingBoundary) {
            return;
        }

        router.delete(`/basins/${basin.id}/boundary/${pendingBoundary.id}`, {
            preserveScroll: true,
            onStart: () => setDeletingBoundary(true),
            onFinish: () => {
                setDeletingBoundary(false);
                setPendingBoundary(null);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Basin registry' },
                { label: 'Basins', href: '/basins' },
                { label: basin.code },
            ]}
        >
            <Head title={`${basin.code} — ${basin.name}`} />

            <Button href="/basins" variant="ghost" size="sm" className="-ml-2 mb-2">
                <ArrowLeft className="h-3.5 w-3.5" strokeWidth={2} />
                All basins
            </Button>

            <PageHeader
                eyebrow="Basin registry"
                title={`${basin.code} — ${basin.name}`}
                description={basin.name_alt ? `Also recorded as ${basin.name_alt}.` : 'Major river basin record.'}
                actions={
                    <>
                        {abilities.update && (
                            <Button href={`/basins/${basin.id}/edit`} variant="secondary" size="sm">
                                <Pencil className="h-3.5 w-3.5" strokeWidth={1.75} />
                                Edit basin
                            </Button>
                        )}
                        {abilities.delete && (
                            <Button variant="secondary" size="sm" onClick={() => setPendingDelete(true)}>
                                <Trash2 className="h-3.5 w-3.5" strokeWidth={1.75} />
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mt-4 flex flex-wrap items-center gap-1.5">
                <Badge tone={dataStatusTone(basin.data_status)} size="sm">{dataStatusLabel(basin.data_status)} data</Badge>
                <Badge tone={boundary.tone} size="sm">{boundary.label}</Badge>
                <Badge tone={basin.is_active ? 'success' : 'danger'} size="sm">{basin.is_active ? 'Active record' : 'Inactive record'}</Badge>
                {basin.data_source && <Badge tone="brand" size="sm">{basin.data_source.name}</Badge>}
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryTile label="Sub-basins" value={summary.sub_basins} hint="Nested geographic children" />
                <SummaryTile label="Monitoring stations" value={summary.stations} hint="Registered in this basin" />
                <SummaryTile label="Boundary versions" value={summary.boundaries} hint="Authoritative polygons" />
                <SummaryTile label="Documents" value={summary.documents} hint="Supporting records" />
            </div>

            <Card className="mt-4 overflow-hidden">
                <Tabs tabs={tabs} value={tab} onChange={setTab} className="px-2" />

                <div className="px-5 py-5">
                    {tab === 'overview' && (
                        <div className="space-y-6">
                            <section>
                                <h2 className="text-sm font-semibold tracking-tight text-ink">Basin specification</h2>
                                <dl className="mt-3 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <DetailItem label="Basin code"><span className="font-mono">{basin.code}</span></DetailItem>
                                    <DetailItem label="Basin name">{basin.name}</DetailItem>
                                    <DetailItem label="Alternative name">{basin.name_alt || 'Not recorded'}</DetailItem>
                                    <DetailItem label="Geographic area">{formatArea(basin.area_km2)}</DetailItem>
                                    <DetailItem label="Mean elevation">{formatElevation(basin.elevation_m)}</DetailItem>
                                    <DetailItem label="Centroid">
                                        {basin.centroid_lat === null || basin.centroid_lng === null
                                            ? 'Not recorded'
                                            : `${formatCoordinate(basin.centroid_lat)}, ${formatCoordinate(basin.centroid_lng)}`}
                                    </DetailItem>
                                    <DetailItem label="Data source">
                                        {basin.data_source ? (
                                            <span>
                                                {basin.data_source.name}
                                                {basin.data_source.organisation ? ` · ${basin.data_source.organisation}` : ''}
                                            </span>
                                        ) : (
                                            'Not recorded'
                                        )}
                                    </DetailItem>
                                    <DetailItem label="Data status"><Badge tone={dataStatusTone(basin.data_status)} size="sm">{dataStatusLabel(basin.data_status)}</Badge></DetailItem>
                                    <DetailItem label="Record status">{basin.is_active ? 'Active' : 'Inactive'}</DetailItem>
                                    <DetailItem label="Created">{formatDateTime(basin.created_at)}</DetailItem>
                                    <DetailItem label="Last updated">{formatDateTime(basin.updated_at)}</DetailItem>
                                </dl>
                            </section>

                            {basin.description && (
                                <section>
                                    <h2 className="text-sm font-semibold tracking-tight text-ink">Description</h2>
                                    <p className="mt-2 max-w-3xl text-[13px] leading-relaxed text-muted">{basin.description}</p>
                                </section>
                            )}

                            {basin.source_note && (
                                <section className="rounded-lg border border-line bg-canvas/50 px-4 py-3">
                                    <h2 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Source note</h2>
                                    <p className="mt-1.5 text-[12.5px] leading-relaxed text-ink">{basin.source_note}</p>
                                </section>
                            )}
                        </div>
                    )}

                    {tab === 'sub-basins' && (
                        <div>
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <p className="text-[13px] text-muted">Every sub-basin belongs to exactly one parent basin.</p>
                                {can('create sub-basins') && (
                                    <Button href={`/sub-basins/create?basin_id=${basin.id}`} size="sm" variant="secondary">
                                        <Plus className="h-3.5 w-3.5" strokeWidth={2} />
                                        Add sub-basin
                                    </Button>
                                )}
                            </div>

                            {subBasins.length === 0 ? (
                                <EmptyState
                                    icon={GitBranch}
                                    title="No sub-basins registered"
                                    description="Sub-basins added to this basin appear here with their stations and boundary status."
                                />
                            ) : (
                                <div className="overflow-x-auto rounded-lg border border-line">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                            <tr>
                                                <th scope="col" className={cn(cellHead, 'pl-4')}>Code</th>
                                                <th scope="col" className={cellHead}>Name</th>
                                                <th scope="col" className={cn(cellHead, 'text-right')}>Area (km²)</th>
                                                <th scope="col" className={cn(cellHead, 'text-right')}>Stations</th>
                                                <th scope="col" className={cellHead}>Boundary</th>
                                                <th scope="col" className={cellHead}>Data status</th>
                                                <th scope="col" className={cn(cellHead, 'pr-4 text-right')}>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {subBasins.map((subBasin) => {
                                                const subBoundary = boundaryStatusMeta(subBasin.boundary_status);

                                                return (
                                                    <tr key={subBasin.id} className="hover:bg-canvas/40">
                                                        <td className="whitespace-nowrap px-4 py-2.5 pl-4 font-mono text-[12px] text-ink">{subBasin.code}</td>
                                                        <td className="px-4 py-2.5 text-[13px] text-ink">
                                                            {subBasin.name}
                                                            {!subBasin.is_active && <Badge tone="danger" size="sm" className="ml-2">Inactive</Badge>}
                                                        </td>
                                                        <td className="px-4 py-2.5 text-right text-[12.5px] tabular text-muted">
                                                            {subBasin.area_km2 === null ? '—' : formatNumber(subBasin.area_km2, { maximumFractionDigits: 2 })}
                                                        </td>
                                                        <td className="px-4 py-2.5 text-right text-[12.5px] tabular text-muted">{formatNumber(subBasin.stations_count)}</td>
                                                        <td className="px-4 py-2.5"><Badge tone={subBoundary.tone} size="sm">{subBoundary.short}</Badge></td>
                                                        <td className="px-4 py-2.5"><Badge tone={dataStatusTone(subBasin.data_status)} size="sm">{dataStatusLabel(subBasin.data_status)}</Badge></td>
                                                        <td className="px-4 py-2.5 pr-4 text-right">
                                                            <Button href={`/sub-basins/${subBasin.id}`} variant="ghost" size="sm">View</Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {tab === 'stations' && (
                        <div>
                            {stations.data.length === 0 ? (
                                <EmptyState
                                    icon={RadioTower}
                                    title="No monitoring stations in this basin"
                                    description="Stations are registered in Phase 7 and will appear here with their coordinates and parameters."
                                />
                            ) : (
                                <div className="overflow-x-auto rounded-lg border border-line">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                            <tr>
                                                <th scope="col" className={cn(cellHead, 'pl-4')}>Code</th>
                                                <th scope="col" className={cellHead}>Station</th>
                                                <th scope="col" className={cellHead}>Type</th>
                                                <th scope="col" className={cellHead}>Sub-basin</th>
                                                <th scope="col" className={cellHead}>Coordinates</th>
                                                <th scope="col" className={cn(cellHead, 'text-right')}>Elevation</th>
                                                <th scope="col" className={cn(cellHead, 'pr-4')}>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {stations.data.map((station) => (
                                                <tr key={station.id} className="hover:bg-canvas/40">
                                                    <td className="whitespace-nowrap px-4 py-2.5 pl-4 font-mono text-[12px] text-ink">{station.code}</td>
                                                    <td className="px-4 py-2.5 text-[13px] text-ink">{station.name}</td>
                                                    <td className="px-4 py-2.5 text-[12.5px] text-muted">{stationTypeLabel(station.station_type)}</td>
                                                    <td className="px-4 py-2.5 text-[12.5px] text-muted">{station.sub_basin?.name ?? '—'}</td>
                                                    <td className="whitespace-nowrap px-4 py-2.5 text-[12px] tabular text-muted">
                                                        {station.latitude === null ? '—' : `${formatCoordinate(station.latitude)}, ${formatCoordinate(station.longitude)}`}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-right text-[12.5px] tabular text-muted">
                                                        {station.elevation_m === null ? '—' : `${formatNumber(station.elevation_m, { maximumFractionDigits: 0 })} m`}
                                                    </td>
                                                    <td className="px-4 py-2.5 pr-4">
                                                        <Badge tone={station.is_active ? 'success' : 'danger'} size="sm">{station.is_active ? 'Active' : 'Inactive'}</Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <Pagination meta={stations} className="mt-3 rounded-lg border border-line" />
                        </div>
                    )}

                    {tab === 'hydrology' && (
                        <div>
                            {observations.length === 0 ? (
                                <EmptyState
                                    icon={Database}
                                    title="No hydrological readings linked yet"
                                    description="Observation storage, Excel import and quality control arrive in Phases 8 and 9. Records will then be listed here with their parameter, unit and quality status."
                                />
                            ) : (
                                <div className="overflow-x-auto rounded-lg border border-line">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                            <tr>
                                                <th scope="col" className={cn(cellHead, 'pl-4')}>Station</th>
                                                <th scope="col" className={cellHead}>Parameter</th>
                                                <th scope="col" className={cn(cellHead, 'text-right')}>Value</th>
                                                <th scope="col" className={cellHead}>Measured at</th>
                                                <th scope="col" className={cn(cellHead, 'pr-4')}>Quality</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {observations.map((observation) => (
                                                <tr key={observation.id} className="hover:bg-canvas/40">
                                                    <td className="px-4 py-2.5 pl-4 text-[12.5px] text-ink">
                                                        <span className="font-mono text-[11.5px] text-muted">{observation.station?.code}</span>{' '}
                                                        {observation.station?.name}
                                                    </td>
                                                    <td className="px-4 py-2.5 text-[12.5px] text-ink">{observation.parameter?.name}</td>
                                                    <td className="px-4 py-2.5 text-right text-[12.5px] tabular text-ink">
                                                        {formatNumber(observation.value, { maximumFractionDigits: 3 })} <span className="text-[11px] text-muted">{observation.unit}</span>
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2.5 text-[12px] tabular text-muted">{formatDateTime(observation.measured_at)}</td>
                                                    <td className="px-4 py-2.5 pr-4"><QualityBadge status={observation.quality_status} /></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {tab === 'geographic' && (
                        <div className="space-y-6">
                            <section className="grid gap-4 lg:grid-cols-3">
                                <Card className="p-4 lg:col-span-1">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Boundary state</p>
                                    <div className="mt-2"><Badge tone={boundary.tone}>{boundary.label}</Badge></div>
                                    <p className="mt-2 text-[12px] leading-relaxed text-muted">{boundary.description}</p>
                                    <dl className="mt-4 space-y-3">
                                        <DetailItem label="Centroid">
                                            {basin.centroid_lat === null || basin.centroid_lng === null
                                                ? 'Not recorded'
                                                : `${formatCoordinate(basin.centroid_lat)}, ${formatCoordinate(basin.centroid_lng)}`}
                                        </DetailItem>
                                        <DetailItem label="Area">{formatArea(basin.area_km2)}</DetailItem>
                                        <DetailItem label="Mean elevation">{formatElevation(basin.elevation_m)}</DetailItem>
                                    </dl>
                                </Card>

                                <Card className="p-4 lg:col-span-2">
                                    <div className="flex items-center gap-2">
                                        <Globe2 className="h-4 w-4 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                                        <p className="text-[13px] font-medium text-ink">Stored display geometry</p>
                                    </div>
                                    {displayBoundary ? (
                                        <>
                                            <p className="mt-1 text-[11.5px] text-muted">
                                                Mirrors the primary polygon · {formatNumber(displayBoundary.length)} characters of WKT
                                            </p>
                                            {displayBoundary.bbox && (
                                                <p className="mt-2 text-[11.5px] tabular text-muted">
                                                    BBox {formatCoordinate(displayBoundary.bbox.min_lng)}, {formatCoordinate(displayBoundary.bbox.min_lat)} →{' '}
                                                    {formatCoordinate(displayBoundary.bbox.max_lng)}, {formatCoordinate(displayBoundary.bbox.max_lat)}
                                                </p>
                                            )}
                                            <details className="mt-3">
                                                <summary className="cursor-pointer text-[12px] font-medium text-brand-700 dark:text-brand-300">Preview WKT</summary>
                                                <pre className="eec-scrollbar mt-2 max-h-40 overflow-auto rounded-lg bg-canvas p-3 text-[11px] leading-relaxed text-muted">
                                                    {displayBoundary.preview}
                                                </pre>
                                            </details>
                                        </>
                                    ) : (
                                        <p className="mt-2 text-[12px] leading-relaxed text-muted">
                                            No simplified display polygon is stored. Import a primary boundary to populate this view; coordinates are never derived from the area or centroid fields.
                                        </p>
                                    )}
                                </Card>
                            </section>

                            <section>
                                <div className="mb-3 flex items-center justify-between gap-2">
                                    <h2 className="text-sm font-semibold tracking-tight text-ink">Boundary versions</h2>
                                    <span className="text-[11px] text-muted">{boundaries.length} on file</span>
                                </div>

                                {boundaries.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-line px-5 py-8 text-center">
                                        <MapPin className="mx-auto h-5 w-5 text-muted" strokeWidth={1.75} />
                                        <p className="mt-2 text-[13px] font-medium text-ink">No polygon has been imported</p>
                                        <p className="mt-1 text-xs text-muted">Import a verified GeoJSON boundary below. Nothing is generated automatically.</p>
                                    </div>
                                ) : (
                                    <ul className="divide-y divide-line rounded-lg border border-line">
                                        {boundaries.map((version) => (
                                            <li key={version.id} className="px-4 py-3.5">
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <p className="text-[13px] font-medium text-ink">{version.name}</p>
                                                            {version.is_primary && <Badge tone="success" size="sm">Primary</Badge>}
                                                            <Badge tone="neutral" size="sm">SRID {version.srid}</Badge>
                                                        </div>
                                                        <p className="mt-1 text-[11.5px] text-muted">
                                                            {version.source || 'Source not recorded'}
                                                            {version.accuracy_m ? ` · ±${formatNumber(version.accuracy_m, { maximumFractionDigits: 2 })} m` : ''}
                                                            {' · '}added {formatDateTime(version.created_at)}
                                                        </p>
                                                        {version.notes && <p className="mt-1 text-[11.5px] leading-relaxed text-muted">{version.notes}</p>}
                                                    </div>

                                                    <div className="flex shrink-0 items-center gap-1">
                                                        {abilities.importBoundary && (
                                                            <Button
                                                                variant="danger-ghost"
                                                                size="icon"
                                                                aria-label={`Remove ${version.name}`}
                                                                onClick={() => setPendingBoundary(version)}
                                                            >
                                                                <Trash2 className="h-4 w-4" strokeWidth={1.75} />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>

                                                <details className="mt-2">
                                                    <summary className="cursor-pointer text-[11.5px] font-medium text-brand-700 dark:text-brand-300">Geometry preview</summary>
                                                    <pre className="eec-scrollbar mt-2 max-h-32 overflow-auto rounded-lg bg-canvas p-3 text-[10.5px] leading-relaxed text-muted">
                                                        {version.wkt_preview}…
                                                    </pre>
                                                    {version.bbox && (
                                                        <p className="mt-1.5 text-[10.5px] tabular text-muted">
                                                            BBox {formatCoordinate(version.bbox.min_lng)}, {formatCoordinate(version.bbox.min_lat)} →{' '}
                                                            {formatCoordinate(version.bbox.max_lng)}, {formatCoordinate(version.bbox.max_lat)}
                                                        </p>
                                                    )}
                                                </details>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            {abilities.importBoundary && (
                                <section className="rounded-lg border border-line bg-canvas/30 p-4">
                                    <BoundaryPanel action={`/basins/${basin.id}/boundary`} />
                                </section>
                            )}
                        </div>
                    )}

                    {tab === 'documents' && (
                        <div>
                            <div className="mb-4 flex items-center gap-2">
                                <FileText className="h-4 w-4 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                                <p className="text-[13px] font-medium text-ink">Supporting documents</p>
                            </div>
                            <DocumentsPanel
                                documents={documents}
                                action={`/basins/${basin.id}/documents`}
                                canManage={abilities.manageDocuments}
                            />
                        </div>
                    )}

                    {tab === 'activity' && (
                        <div>
                            <div className="mb-4 flex items-center gap-2">
                                <Waves className="h-4 w-4 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                                <p className="text-[13px] font-medium text-ink">Record history</p>
                                <span className="text-[11px] text-muted">Includes changes made to sub-basins in this basin.</span>
                            </div>

                            {activity.length === 0 ? (
                                <EmptyState
                                    icon={Waves}
                                    title="No activity recorded yet"
                                    description="Creates, edits, boundary imports and document changes are appended here with the acting user and timestamp."
                                />
                            ) : (
                                <ol className="relative space-y-4 border-l border-line pl-4">
                                    {activity.map((entry) => (
                                        <li key={entry.id} className="relative">
                                            <span className="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full border-2 border-surface bg-brand-500" />
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-[12.5px] font-medium text-ink">{activityLabel(entry.event)}</p>
                                                <span className="text-[11px] text-muted">{formatDateTime(entry.created_at)}</span>
                                            </div>
                                            <p className="mt-0.5 text-[12px] leading-relaxed text-muted">{entry.description}</p>
                                            <p className="mt-0.5 text-[11px] text-muted">{entry.user ?? 'System'}</p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    )}
                </div>
            </Card>

            <ConfirmDialog
                open={pendingDelete}
                title="Delete this basin?"
                description={`${basin.code} — ${basin.name} will be archived. Basins with sub-basins or monitoring stations cannot be deleted until those records are reassigned or removed.`}
                confirmLabel="Delete basin"
                processing={deleting}
                onConfirm={confirmDelete}
                onClose={() => setPendingDelete(false)}
            />

            <ConfirmDialog
                open={Boolean(pendingBoundary)}
                title="Remove this boundary version?"
                description={
                    pendingBoundary
                        ? `"${pendingBoundary.name}" will be removed. ${
                              pendingBoundary.is_primary ? 'The display geometry will be cleared until another primary boundary is imported.' : 'Other versions are unaffected.'
                          }`
                        : undefined
                }
                confirmLabel="Remove boundary"
                processing={deletingBoundary}
                onConfirm={confirmBoundaryDelete}
                onClose={() => setPendingBoundary(null)}
            />
        </AppLayout>
    );
}
