import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card } from '@/Components/Card';
import Can from '@/Components/Can';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Tabs from '@/Components/Tabs';
import DocumentsPanel from '@/Components/geography/DocumentsPanel';
import AppLayout from '@/Layouts/AppLayout';
import { activityLabel, dataStatusLabel, dataStatusTone } from '@/Utils/basins';
import { formatCoordinate, formatDate, formatDateTime, formatElevation, formatNumber } from '@/Utils/format';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Database, FileSpreadsheet, FileText, MapPin, Pencil, RadioTower, Trash2, Waves } from 'lucide-react';
import { useState } from 'react';

function DetailItem({ label, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</dt>
            <dd className="mt-1 text-[13px] leading-relaxed text-ink">{children}</dd>
        </div>
    );
}

function SummaryTile({ label, value, hint }) {
    return (
        <Card className="px-4 py-3.5">
            <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className="mt-1.5 text-xl font-semibold tabular text-ink">{formatNumber(value ?? 0)}</p>
            {hint && <p className="mt-0.5 text-[11px] text-muted">{hint}</p>}
        </Card>
    );
}

export default function Show({ station, observations, importBatches, initialTab = 'overview', documents = [], qualityIssues = [], activity = [], availability = {}, abilities = {} }) {
    const [tab, setTab] = useState(initialTab);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const removeStation = () => {
        router.delete('/stations/' + station.id, {
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setConfirmDelete(false);
            },
        });
    };

    const tabs = [
        { key: 'overview', label: 'Overview' },
        { key: 'location', label: 'Geographic location' },
        { key: 'parameters', label: 'Parameters', count: station.parameters.length },
        { key: 'observations', label: 'Historical observations', count: availability.records },
        { key: 'availability', label: 'Data availability' },
        { key: 'imports', label: 'Data imports', count: importBatches?.total ?? 0 },
        { key: 'documents', label: 'Documents', count: documents.length },
        { key: 'activity', label: 'Activity history', count: activity.length },
    ];

    return (
        <AppLayout breadcrumbs={[{ label: 'Hydrological data' }, { label: 'Monitoring stations', href: '/stations' }, { label: station.code }]}>
            <Head title={station.name} />
            <div className="mb-4">
                <Link href="/stations" className="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-ink"><ArrowLeft className="h-3.5 w-3.5" />All monitoring stations</Link>
            </div>
            <PageHeader
                eyebrow={station.code}
                title={station.name}
                description={station.name_alt ? station.name_alt : 'Monitoring station record and observed data.'}
                actions={
                    <>
                        {station.latitude !== null && station.longitude !== null && (
                            <Can permission="view map">
                                <Button variant="secondary" size="sm" href={'/gis?station=' + station.id}><MapPin className="h-3.5 w-3.5" />Open on map</Button>
                            </Can>
                        )}
                        {abilities.uploadData && <Button variant="primary" size="sm" href={`/stations/${station.id}/data-imports/upload`}><FileSpreadsheet className="h-3.5 w-3.5" />Import data</Button>}
                        {abilities.update && <Button variant="secondary" size="sm" href={'/stations/' + station.id + '/edit'}><Pencil className="h-3.5 w-3.5" />Edit</Button>}
                        {abilities.delete && <Button variant="danger-ghost" size="sm" onClick={() => setConfirmDelete(true)}><Trash2 className="h-3.5 w-3.5" />Archive</Button>}
                    </>
                }
            />

            <div className="mt-4 flex flex-wrap items-center gap-1.5">
                <Badge tone="brand" size="sm">{station.category?.label ?? station.station_type}</Badge>
                <Badge tone={station.is_active ? 'success' : 'danger'} size="sm">{station.is_active ? 'Operational' : 'Inactive'}</Badge>
                <Badge tone={dataStatusTone(station.data_status)} size="sm">{dataStatusLabel(station.data_status)} data</Badge>
                {station.basin && <Badge tone="neutral" size="sm">{station.basin.code} · {station.basin.name}</Badge>}
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryTile label="Observations" value={availability.records} hint="Values registered for this station" />
                <SummaryTile label="Parameters" value={availability.parameters} hint="Configured measurement types" />
                <SummaryTile label="Open quality issues" value={availability.open_issues} hint="Awaiting review" />
                <SummaryTile label="Documents" value={documents.length} hint="Private supporting files" />
            </div>

            <Card className="mt-4 overflow-hidden">
                <Tabs tabs={tabs} value={tab} onChange={setTab} className="px-2" />
                <div className="px-5 py-5">
                    {tab === 'overview' && (
                        <div className="space-y-6">
                            <section>
                                <h2 className="text-sm font-semibold tracking-tight text-ink">Station specification</h2>
                                <dl className="mt-3 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <DetailItem label="Station code"><span className="font-mono">{station.code}</span></DetailItem>
                                    <DetailItem label="Station name">{station.name}</DetailItem>
                                    <DetailItem label="Alternative name">{station.name_alt || 'Not recorded'}</DetailItem>
                                    <DetailItem label="Station type">{station.category?.label ?? station.station_type}</DetailItem>
                                    <DetailItem label="Managing organization">{station.operator || 'Not recorded'}</DetailItem>
                                    <DetailItem label="Installation date">{formatDate(station.established_on)}</DetailItem>
                                    <DetailItem label="Basin">{station.basin ? station.basin.code + ' — ' + station.basin.name : 'Not recorded'}</DetailItem>
                                    <DetailItem label="Sub-basin">{station.sub_basin ? station.sub_basin.code + ' — ' + station.sub_basin.name : 'Not assigned'}</DetailItem>
                                    <DetailItem label="Watershed">{station.watershed ? station.watershed.code + ' — ' + station.watershed.name : 'Not assigned'}</DetailItem>
                                    <DetailItem label="Coordinates">{station.latitude === null || station.longitude === null ? 'Not recorded' : formatCoordinate(station.latitude) + ', ' + formatCoordinate(station.longitude)}</DetailItem>
                                    <DetailItem label="Elevation">{formatElevation(station.elevation_m)}</DetailItem>
                                    <DetailItem label="Data source">{station.data_source ? station.data_source.name + (station.data_source.organisation ? ' · ' + station.data_source.organisation : '') : 'Not recorded'}</DetailItem>
                                    <DetailItem label="Data status"><Badge tone={dataStatusTone(station.data_status)} size="sm">{dataStatusLabel(station.data_status)}</Badge></DetailItem>
                                    <DetailItem label="Operational status">{station.is_active ? 'Operational' : 'Inactive'}</DetailItem>
                                    <DetailItem label="Registered">{formatDateTime(station.created_at)}</DetailItem>
                                    <DetailItem label="Last updated">{formatDateTime(station.updated_at)}</DetailItem>
                                </dl>
                            </section>
                            {station.notes && (
                                <section>
                                    <h2 className="text-sm font-semibold tracking-tight text-ink">Description and notes</h2>
                                    <p className="mt-2 max-w-3xl whitespace-pre-line text-[13px] leading-relaxed text-muted">{station.notes}</p>
                                </section>
                            )}
                        </div>
                    )}

                    {tab === 'location' && (
                        <div className="space-y-5">
                            <section>
                                <h2 className="text-sm font-semibold tracking-tight text-ink">Geographic location</h2>
                                {station.latitude === null || station.longitude === null ? (
                                    <EmptyState icon={MapPin} title="Coordinates not recorded" description="Add confirmed WGS84 coordinates to place this station on the GIS map." />
                                ) : (
                                    <>
                                        <dl className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                            <DetailItem label="Latitude">{formatCoordinate(station.latitude)}</DetailItem>
                                            <DetailItem label="Longitude">{formatCoordinate(station.longitude)}</DetailItem>
                                            <DetailItem label="Elevation">{formatElevation(station.elevation_m)}</DetailItem>
                                            <DetailItem label="Basin">{station.basin ? station.basin.code + ' — ' + station.basin.name : 'Not recorded'}</DetailItem>
                                            <DetailItem label="Sub-basin">{station.sub_basin?.name ?? 'Not assigned'}</DetailItem>
                                            <DetailItem label="Watershed">{station.watershed?.name ?? 'Not assigned'}</DetailItem>
                                        </dl>
                                        <Can permission="view map">
                                            <Button href={'/gis?station=' + station.id} size="sm" className="mt-5"><MapPin className="h-4 w-4" />Open station on GIS map</Button>
                                        </Can>
                                    </>
                                )}
                            </section>
                        </div>
                    )}

                    {tab === 'parameters' && (
                        <div>
                            {station.parameters.length === 0 ? (
                                <EmptyState icon={Waves} title="No parameters configured" description="Edit this station to associate the measurements it is set up to record." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-left text-sm">
                                        <thead className="border-b border-line bg-canvas/50"><tr><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Parameter</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Unit</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Sensor reference</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Period</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Status</th></tr></thead>
                                        <tbody className="divide-y divide-line">
                                            {station.parameters.map((parameter) => (
                                                <tr key={parameter.id}>
                                                    <td className="px-3 py-3"><span className="block font-medium text-ink">{parameter.name}</span><span className="font-mono text-[10.5px] text-muted">{parameter.code}{parameter.symbol ? ' · ' + parameter.symbol : ''}</span></td>
                                                    <td className="px-3 py-3 text-[12px] text-ink">{parameter.unit || 'Not set'}</td>
                                                    <td className="px-3 py-3 font-mono text-[11px] text-muted">{parameter.sensor_reference || 'Not recorded'}</td>
                                                    <td className="px-3 py-3 text-[11px] text-muted">{parameter.starts_on ? formatDate(parameter.starts_on) : '—'}{parameter.ends_on ? ' to ' + formatDate(parameter.ends_on) : ''}</td>
                                                    <td className="px-3 py-3"><Badge tone={parameter.is_active ? 'success' : 'neutral'} size="sm">{parameter.is_active ? 'Active' : 'Inactive'}</Badge></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {tab === 'observations' && (
                        <div>
                            {observations.data.length === 0 ? (
                                <EmptyState icon={Database} title="No historical observations" description="Measurements for this station will appear here after data is recorded or imported." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-left text-sm">
                                        <thead className="border-b border-line bg-canvas/50"><tr><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Date and time</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Parameter</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Value</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Quality</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Source</th></tr></thead>
                                        <tbody className="divide-y divide-line">
                                            {observations.data.map((observation) => (
                                                <tr key={observation.id}>
                                                    <td className="px-3 py-2.5 text-[12px] text-ink">{formatDateTime(observation.measured_at)}</td>
                                                    <td className="px-3 py-2.5 text-[12px] text-ink">{observation.parameter?.name ?? 'Unknown parameter'}</td>
                                                    <td className="px-3 py-2.5 tabular text-[12px] text-ink">{formatNumber(Number(observation.value))} {observation.unit ?? ''}</td>
                                                    <td className="px-3 py-2.5"><Badge tone={observation.quality_status === 'approved' ? 'success' : observation.quality_status === 'flagged' ? 'warning' : 'neutral'} size="sm">{observation.quality_status}</Badge>{observation.quality_issues_count > 0 && <span className="ml-1 text-[10px] text-amber-700">{observation.quality_issues_count} issue(s)</span>}</td>
                                                    <td className="px-3 py-2.5 text-[11px] text-muted">{observation.data_source || 'Not recorded'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <Pagination meta={observations} />
                        </div>
                    )}

                    {tab === 'availability' && (
                        <div className="space-y-6">
                            <section>
                                <h2 className="text-sm font-semibold tracking-tight text-ink">Historical coverage</h2>
                                {availability.records === 0 ? (
                                    <EmptyState icon={CalendarClock} title="No data coverage yet" description="The station has no observation records in the system." />
                                ) : (
                                    <dl className="mt-4 grid gap-5 sm:grid-cols-3">
                                        <DetailItem label="First observation">{formatDateTime(availability.first_at)}</DetailItem>
                                        <DetailItem label="Most recent observation">{formatDateTime(availability.last_at)}</DetailItem>
                                        <DetailItem label="Total observations">{formatNumber(availability.records)}</DetailItem>
                                    </dl>
                                )}
                            </section>
                            <section>
                                <h2 className="text-sm font-semibold tracking-tight text-ink">Data quality issues</h2>
                                {qualityIssues.length === 0 ? (
                                    <EmptyState icon={Waves} title="No quality issues recorded" description="Issues associated with station observations will appear here." />
                                ) : (
                                    <ul className="mt-3 divide-y divide-line">
                                        {qualityIssues.map((issue) => (
                                            <li key={issue.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0">
                                                    <p className="text-[13px] font-medium text-ink">{issue.description}</p>
                                                    <p className="mt-1 text-[11px] text-muted">{issue.parameter || 'Station data'} · {issue.issue_type.replaceAll('_', ' ')} · {formatDateTime(issue.created_at)}</p>
                                                </div>
                                                <div className="flex shrink-0 gap-1.5"><Badge tone={issue.severity === 'critical' || issue.severity === 'high' ? 'danger' : 'warning'} size="sm">{issue.severity}</Badge><Badge tone={issue.status === 'resolved' ? 'success' : 'neutral'} size="sm">{issue.status}</Badge></div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        </div>
                    )}

                    {tab === 'documents' && (
                        <DocumentsPanel documents={documents} action={'/stations/' + station.id + '/documents'} canManage={abilities.manageDocuments} />
                    )}

                    {tab === 'imports' && (
                        <section>
                            {importBatches?.data?.length ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-left text-sm">
                                            <thead className="border-b border-line bg-canvas/50"><tr><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Workbook</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Uploaded</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Status</th><th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Rows</th><th className="px-3 py-2" /></tr></thead>
                                            <tbody className="divide-y divide-line">
                                                {importBatches.data.map((batch) => (
                                                    <tr key={batch.uuid}>
                                                        <td className="px-3 py-2.5"><span className="block text-[12px] font-medium text-ink">{batch.file_name}</span><span className="text-[10px] text-muted">{batch.uploaded_by || 'Unknown user'}</span></td>
                                                        <td className="px-3 py-2.5 text-[11px] text-muted">{formatDateTime(batch.created_at)}</td>
                                                        <td className="px-3 py-2.5"><Badge tone={batch.status === 'completed_with_errors' ? 'warning' : batch.stage === 'imported' ? 'success' : batch.status === 'failed' ? 'danger' : 'neutral'} size="sm">{batch.status === 'completed_with_errors' ? 'Imported with errors' : batch.stage.replaceAll('_', ' ')}</Badge></td>
                                                        <td className="px-3 py-2.5 tabular text-[11px] text-muted">{formatNumber(batch.imported_rows)} imported · {formatNumber(batch.duplicate_rows)} duplicates · {formatNumber(batch.rejected_rows)} rejected</td>
                                                        <td className="px-3 py-2.5 text-right"><div className="flex justify-end gap-3">{abilities.exportData && <button type="button" onClick={() => window.location.assign(`/excel/${batch.uuid}/export/column-based`)} className="text-[11px] font-medium text-muted hover:text-ink">Column-based</button>}{batch.stage !== 'imported' && <Link href={['validated', 'compared'].includes(batch.stage) ? `/excel/${batch.uuid}/validation` : `/excel/${batch.uuid}/preview`} className="text-[11px] font-medium text-brand-700 hover:text-brand-900 dark:text-brand-300">Continue import</Link>}</div></td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pagination meta={importBatches} />
                                </>
                            ) : (
                                <EmptyState icon={FileSpreadsheet} title="No station data imports yet" description="Raw workbooks uploaded for this station will appear here with their transformation and import results." />
                            )}
                        </section>
                    )}

                    {tab === 'activity' && (
                        <section>
                            {activity.length === 0 ? (
                                <EmptyState icon={FileText} title="No station activity yet" description="Station registrations, changes, and document actions will appear in this audit history." />
                            ) : (
                                <ol className="divide-y divide-line">
                                    {activity.map((item) => (
                                        <li key={item.id} className="flex gap-3 py-3">
                                            <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-[13px] font-medium text-ink">{activityLabel(item.event)}</p>
                                                <p className="mt-0.5 text-[12px] text-muted">{item.description || 'Station record updated'}{item.user ? ' · ' + item.user : ''}</p>
                                            </div>
                                            <time className="shrink-0 text-[10.5px] text-muted">{formatDateTime(item.created_at)}</time>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    )}
                </div>
            </Card>

            <ConfirmDialog
                open={confirmDelete}
                title="Archive this station?"
                description={'Station ' + station.code + ' will be removed from active lists. Its observation and audit history will be retained.'}
                confirmLabel="Archive station"
                processing={deleting}
                onConfirm={removeStation}
                onClose={() => setConfirmDelete(false)}
            />
        </AppLayout>
    );
}
