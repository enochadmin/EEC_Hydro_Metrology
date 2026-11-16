import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardHeader } from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import BoundaryPanel from '@/Components/geography/BoundaryPanel';
import DocumentsPanel from '@/Components/geography/DocumentsPanel';
import AppLayout from '@/Layouts/AppLayout';
import { activityLabel, boundaryStatusMeta, dataStatusLabel, dataStatusTone, stationTypeLabel } from '@/Utils/basins';
import { cn } from '@/Utils/cn';
import { formatArea, formatCoordinate, formatDateTime, formatElevation, formatNumber } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileText, GitBranch, Globe2, Pencil, RadioTower, Trash2, Waves } from 'lucide-react';
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

export default function Show({ subBasin, stations, geometry = null, documents = [], activity = [], summary = {}, abilities = {} }) {
    const [pendingDelete, setPendingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [confirmBoundary, setConfirmBoundary] = useState(false);
    const [clearingBoundary, setClearingBoundary] = useState(false);

    const boundary = boundaryStatusMeta(subBasin.boundary_status);

    const confirmDelete = () => {
        router.delete(`/sub-basins/${subBasin.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(false);
            },
        });
    };

    const clearBoundary = () => {
        router.delete(`/sub-basins/${subBasin.id}/boundary`, {
            preserveScroll: true,
            onStart: () => setClearingBoundary(true),
            onFinish: () => {
                setClearingBoundary(false);
                setConfirmBoundary(false);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Basin registry' },
                { label: 'Sub-basins', href: '/sub-basins' },
                { label: subBasin.code },
            ]}
        >
            <Head title={`${subBasin.code} — ${subBasin.name}`} />

            <Button href="/sub-basins" variant="ghost" size="sm" className="-ml-2 mb-2">
                <ArrowLeft className="h-3.5 w-3.5" strokeWidth={2} />
                All sub-basins
            </Button>

            <PageHeader
                eyebrow="Basin registry"
                title={`${subBasin.code} — ${subBasin.name}`}
                description={
                    subBasin.basin ? (
                        <span>
                            Sub-basin of{' '}
                            <a href={`/basins/${subBasin.basin.id}`} className="font-medium text-brand-700 underline-offset-2 hover:underline dark:text-brand-300">
                                {subBasin.basin.code} — {subBasin.basin.name}
                            </a>
                            .
                        </span>
                    ) : (
                        'Sub-basin record.'
                    )
                }
                actions={
                    <>
                        {abilities.update && (
                            <Button href={`/sub-basins/${subBasin.id}/edit`} variant="secondary" size="sm">
                                <Pencil className="h-3.5 w-3.5" strokeWidth={1.75} />
                                Edit sub-basin
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
                <Badge tone={dataStatusTone(subBasin.data_status)} size="sm">{dataStatusLabel(subBasin.data_status)} data</Badge>
                <Badge tone={boundary.tone} size="sm">{boundary.label}</Badge>
                <Badge tone={subBasin.is_active ? 'success' : 'danger'} size="sm">{subBasin.is_active ? 'Active record' : 'Inactive record'}</Badge>
                {subBasin.data_source && <Badge tone="brand" size="sm">{subBasin.data_source.name}</Badge>}
            </div>

            <div className="mt-6 grid gap-3 sm:grid-cols-3">
                {[
                    { label: 'Monitoring stations', value: summary.stations, hint: 'Assigned to this sub-basin' },
                    { label: 'Hydrological readings', value: summary.observations, hint: 'Stored against those stations' },
                    { label: 'Documents', value: summary.documents, hint: 'Supporting records' },
                ].map((item) => (
                    <Card key={item.label} className="px-4 py-3.5">
                        <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{item.label}</p>
                        <p className="mt-1.5 text-xl font-semibold tabular text-ink">{formatNumber(item.value)}</p>
                        <p className="mt-0.5 text-[11px] text-muted">{item.hint}</p>
                    </Card>
                ))}
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[1.15fr_1fr]">
                <Card className="overflow-hidden">
                    <CardHeader icon={GitBranch} title="Sub-basin specification" description="Values are shown exactly as recorded; empty fields are never inferred." />
                    <dl className="grid gap-x-6 gap-y-5 px-5 py-4 sm:grid-cols-2">
                        <DetailItem label="Code"><span className="font-mono">{subBasin.code}</span></DetailItem>
                        <DetailItem label="Name">{subBasin.name}</DetailItem>
                        <DetailItem label="Alternative name">{subBasin.name_alt || 'Not recorded'}</DetailItem>
                        <DetailItem label="Parent basin">{subBasin.basin ? `${subBasin.basin.code} — ${subBasin.basin.name}` : 'Not recorded'}</DetailItem>
                        <DetailItem label="Area">{formatArea(subBasin.area_km2)}</DetailItem>
                        <DetailItem label="Mean elevation">{formatElevation(subBasin.elevation_m)}</DetailItem>
                        <DetailItem label="Centroid">
                            {subBasin.centroid_lat === null || subBasin.centroid_lng === null
                                ? 'Not recorded'
                                : `${formatCoordinate(subBasin.centroid_lat)}, ${formatCoordinate(subBasin.centroid_lng)}`}
                        </DetailItem>
                        <DetailItem label="Data source">{subBasin.data_source ? subBasin.data_source.name : 'Not recorded'}</DetailItem>
                        <DetailItem label="Created">{formatDateTime(subBasin.created_at)}</DetailItem>
                        <DetailItem label="Last updated">{formatDateTime(subBasin.updated_at)}</DetailItem>
                    </dl>
                    {(subBasin.description || subBasin.source_note) && (
                        <div className="border-t border-line px-5 py-4">
                            {subBasin.description && <p className="text-[12.5px] leading-relaxed text-muted">{subBasin.description}</p>}
                            {subBasin.source_note && (
                                <p className={cn('text-[11.5px] leading-relaxed text-muted', subBasin.description && 'mt-2')}>
                                    <span className="font-semibold uppercase tracking-[0.1em]">Source note:</span> {subBasin.source_note}
                                </p>
                            )}
                        </div>
                    )}
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader icon={Globe2} title="Geographic boundary" description="WGS84 Polygon or MultiPolygon stored with SRID 4326." />
                    <div className="space-y-3 px-5 py-4">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge tone={boundary.tone} size="sm">{boundary.label}</Badge>
                            {geometry && <Badge tone="neutral" size="sm">{formatNumber(geometry.length)} WKT chars</Badge>}
                        </div>
                        <p className="text-[12px] leading-relaxed text-muted">{boundary.description}</p>

                        {geometry ? (
                            <>
                                {geometry.bbox && (
                                    <p className="text-[11.5px] tabular text-muted">
                                        BBox {formatCoordinate(geometry.bbox.min_lng)}, {formatCoordinate(geometry.bbox.min_lat)} →{' '}
                                        {formatCoordinate(geometry.bbox.max_lng)}, {formatCoordinate(geometry.bbox.max_lat)}
                                    </p>
                                )}
                                <details>
                                    <summary className="cursor-pointer text-[12px] font-medium text-brand-700 dark:text-brand-300">Preview WKT</summary>
                                    <pre className="eec-scrollbar mt-2 max-h-40 overflow-auto rounded-lg bg-canvas p-3 text-[11px] leading-relaxed text-muted">
                                        {geometry.preview}…
                                    </pre>
                                </details>
                                {abilities.importBoundary && (
                                    <Button variant="ghost" size="sm" onClick={() => setConfirmBoundary(true)}>
                                        <Trash2 className="h-3.5 w-3.5" strokeWidth={1.75} />
                                        Remove boundary
                                    </Button>
                                )}
                            </>
                        ) : (
                            <p className="text-[12px] leading-relaxed text-muted">
                                No polygon has been imported for this sub-basin. The boundary is drawn from a GeoJSON file, never generated from the area or centroid.
                            </p>
                        )}
                    </div>

                    {abilities.importBoundary && (
                        <div className="border-t border-line bg-canvas/30 px-5 py-4">
                            <BoundaryPanel
                                action={`/sub-basins/${subBasin.id}/boundary`}
                                description="Upload one GeoJSON Polygon or MultiPolygon in WGS84. Importing again replaces the stored polygon and keeps the previous entry in the activity history."
                            />
                        </div>
                    )}
                </Card>
            </div>

            <Card className="mt-4 overflow-hidden">
                <CardHeader icon={RadioTower} title="Associated monitoring stations" description="Stations assigned to this sub-basin. Basin assignment remains the authoritative parent." />
                {stations.data.length === 0 ? (
                    <EmptyState
                        icon={RadioTower}
                        title="No stations assigned"
                        description="Station management arrives in Phase 7. Once registered, stations that select this sub-basin appear here."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                <tr>
                                    <th scope="col" className={cn(cellHead, 'pl-5')}>Code</th>
                                    <th scope="col" className={cellHead}>Station</th>
                                    <th scope="col" className={cellHead}>Type</th>
                                    <th scope="col" className={cellHead}>Coordinates</th>
                                    <th scope="col" className={cn(cellHead, 'text-right')}>Elevation</th>
                                    <th scope="col" className={cn(cellHead, 'pr-5')}>Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {stations.data.map((station) => (
                                    <tr key={station.id} className="hover:bg-canvas/40">
                                        <td className="whitespace-nowrap px-4 py-2.5 pl-5 font-mono text-[12px] text-ink">{station.code}</td>
                                        <td className="px-4 py-2.5 text-[13px] text-ink">{station.name}</td>
                                        <td className="px-4 py-2.5 text-[12.5px] text-muted">{stationTypeLabel(station.station_type)}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-[12px] tabular text-muted">
                                            {station.latitude === null ? '—' : `${formatCoordinate(station.latitude)}, ${formatCoordinate(station.longitude)}`}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-[12.5px] tabular text-muted">
                                            {station.elevation_m === null ? '—' : `${formatNumber(station.elevation_m, { maximumFractionDigits: 0 })} m`}
                                        </td>
                                        <td className="px-4 py-2.5 pr-5">
                                            <Badge tone={station.is_active ? 'success' : 'danger'} size="sm">{station.is_active ? 'Active' : 'Inactive'}</Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination meta={stations} />
            </Card>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card className="overflow-hidden">
                    <CardHeader icon={FileText} title="Documents" description="Supporting files attached to this sub-basin." />
                    <div className="px-5 py-4">
                        <DocumentsPanel documents={documents} action={`/sub-basins/${subBasin.id}/documents`} canManage={abilities.manageDocuments} />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader icon={Waves} title="Activity history" description="Append-only audit trail for this record." />
                    <div className="px-5 py-4">
                        {activity.length === 0 ? (
                            <EmptyState icon={Waves} title="No activity yet" description="Changes to this sub-basin will be listed here." />
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
                </Card>
            </div>

            <ConfirmDialog
                open={pendingDelete}
                title="Delete this sub-basin?"
                description={`${subBasin.code} — ${subBasin.name} will be archived. Sub-basins with monitoring stations cannot be deleted until those stations are reassigned.`}
                confirmLabel="Delete sub-basin"
                processing={deleting}
                onConfirm={confirmDelete}
                onClose={() => setPendingDelete(false)}
            />

            <ConfirmDialog
                open={confirmBoundary}
                title="Remove the boundary?"
                description="The stored polygon will be cleared from this sub-basin. The activity history keeps a record of the removal."
                confirmLabel="Remove boundary"
                processing={clearingBoundary}
                onConfirm={clearBoundary}
                onClose={() => setConfirmBoundary(false)}
            />
        </AppLayout>
    );
}
