import Badge from '@/Components/Badge';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import ChartCard from '@/Components/reports/ChartCard';
import CoverageTiles from '@/Components/reports/CoverageTiles';
import ExportMenu from '@/Components/reports/ExportMenu';
import TimeSeriesChart from '@/Components/reports/TimeSeriesChart';
import AppLayout from '@/Layouts/AppLayout';
import { stationTypeLabel } from '@/Utils/basins';
import { formatCoordinate, formatDateTime, formatDate, formatElevation, formatNumber } from '@/Utils/format';
import { STATUS_TONES, formatPercent } from '@/Utils/reports';
import { Head, Link } from '@inertiajs/react';
import { Activity, BarChart3, Compass, FileText, ShieldCheck, Waves } from 'lucide-react';

function Field({ label, value }) {
    return (
        <div className="border-b border-line/60 py-2 last:border-0">
            <dt className="text-[10.5px] font-medium uppercase tracking-[0.1em] text-muted">{label}</dt>
            <dd className="mt-0.5 text-[12.5px] text-ink">{value}</dd>
        </div>
    );
}

export default function StationReport({ report, trend, abilities = {} }) {
    const { station, parameters, coverage, quality, recent } = report;

    const provisional = quality.pending + quality.flagged + quality.rejected;
    const restricted = provisional > 0 && !abilities.export_restricted;

    return (
        <AppLayout breadcrumbs={[{ label: 'Reports', href: '/reports' }, { label: station.name }]}>
            <Head title={`${station.name} station report`} />

            <PageHeader
                title={station.name}
                description={`Station report · ${station.code} · ${stationTypeLabel(station.type)}`}
                actions={
                    <ExportMenu
                        baseUrl={`/reports/stations/${station.id}/export`}
                        formats={['csv', 'xlsx', 'pdf', 'geojson']}
                        disabled={!abilities.export || restricted}
                        disabledNote={
                            restricted
                                ? 'This report includes provisional observations; the export restricted reports permission is required.'
                                : undefined
                        }
                    />
                }
            />

            <CoverageTiles
                className="mt-6"
                items={[
                    { label: 'Observations', value: formatNumber(coverage.records) },
                    { label: 'Parameters', value: formatNumber(report.counts.parameters) },
                    {
                        label: 'Record span',
                        value: coverage.first_at ? `${formatDate(coverage.first_at)} – ${formatDate(coverage.last_at)}` : '—',
                        hint: coverage.span_days ? `${formatNumber(coverage.span_days)} calendar days` : undefined,
                    },
                    {
                        label: 'Days without records',
                        value: formatNumber(coverage.missing_days),
                        hint: coverage.span_days ? `${formatPercent(coverage.missing_percent ?? (coverage.missing_days / coverage.span_days) * 100)} of the span` : undefined,
                        tone: coverage.missing_days > 0 ? 'warning' : 'success',
                    },
                ]}
            />

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader icon={FileText} title="Station details" />
                    <CardBody>
                        <dl className="grid gap-x-6 sm:grid-cols-2">
                            <Field label="Code" value={station.code} />
                            <Field label="Alternative name" value={station.name_alt ?? '—'} />
                            <Field
                                label="Basin"
                                value={
                                    station.basin ? (
                                        <Link href={`/reports/basins/${station.basin_id}`} className="text-brand-700 hover:underline dark:text-brand-300">
                                            {station.basin}
                                        </Link>
                                    ) : (
                                        '—'
                                    )
                                }
                            />
                            <Field label="Sub-basin" value={station.sub_basin ?? '—'} />
                            <Field label="Watershed" value={station.watershed ?? '—'} />
                            <Field label="Operator" value={station.operator ?? '—'} />
                            <Field label="Elevation" value={formatElevation(station.elevation_m)} />
                            <Field label="Established" value={formatDate(station.established_on)} />
                            <Field label="Latitude" value={formatCoordinate(station.latitude)} />
                            <Field label="Longitude" value={formatCoordinate(station.longitude)} />
                            <Field label="Record status" value={<Badge size="sm" tone={STATUS_TONES[station.data_status] ?? 'neutral'}>{station.data_status}</Badge>} />
                            <Field label="Active" value={station.is_active ? 'Yes' : 'No'} />
                        </dl>
                        {station.notes && <p className="mt-3 text-[12px] leading-relaxed text-muted">{station.notes}</p>}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader icon={ShieldCheck} title="Quality indicators" />
                    <CardBody className="space-y-2.5">
                        {[
                            ['Approved', formatNumber(quality.approved)],
                            ['Pending review', formatNumber(quality.pending)],
                            ['Flagged', formatNumber(quality.flagged)],
                            ['Rejected', formatNumber(quality.rejected)],
                            ['Approved share', formatPercent(quality.approved_percent)],
                            ['Open quality issues', formatNumber(quality.open_issues)],
                        ].map(([label, value]) => (
                            <div key={label} className="flex items-center justify-between gap-3">
                                <span className="text-[12px] text-muted">{label}</span>
                                <span className="text-[12px] font-medium tabular text-ink">{value}</span>
                            </div>
                        ))}
                    </CardBody>
                </Card>
            </div>

            <div className="mt-4">
                <ChartCard
                    icon={BarChart3}
                    title="Monthly trend"
                    description={trend ? `${trend.profile.name} · ${trend.metric_label}${trend.profile.unit ? ` (${trend.profile.unit})` : ''}` : 'No parameters are registered for this station.'}
                    empty={!trend || trend.points.length === 0}
                    emptyMessage="No approved observations are stored for this station, so no trend can be drawn."
                >
                    {trend && (
                        <TimeSeriesChart points={trend.points} unit={trend.profile.unit} decimals={trend.profile.decimals} height={280} />
                    )}
                </ChartCard>
            </div>

            <Card className="mt-4">
                <CardHeader icon={Waves} title="Available parameters" description="Records, span and gaps for each instrument." />
                {parameters.length === 0 ? (
                    <CardBody>
                        <p className="py-6 text-center text-[12px] text-muted">No parameters are registered for this station.</p>
                    </CardBody>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">Parameter</th>
                                    <th className="px-5 py-2.5">Unit</th>
                                    <th className="px-5 py-2.5">Value type</th>
                                    <th className="px-5 py-2.5 text-right">Records</th>
                                    <th className="px-5 py-2.5 text-right">Days observed</th>
                                    <th className="px-5 py-2.5">Span</th>
                                    <th className="px-5 py-2.5 text-right">Missing</th>
                                </tr>
                            </thead>
                            <tbody>
                                {parameters.map((row) => (
                                    <tr key={row.parameter_id} className="border-b border-line/70 last:border-0">
                                        <td className="px-5 py-2.5 text-[12.5px] font-medium text-ink">{row.name}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{row.unit ?? '—'}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{row.value_type.replace(/_/g, ' ')}</td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatNumber(row.records)}</td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatNumber(row.observed_days)}</td>
                                        <td className="px-5 py-2.5 text-[11.5px] text-muted">
                                            {row.first_at ? `${formatDate(row.first_at)} – ${formatDate(row.last_at)}` : 'No records'}
                                        </td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatPercent(row.missing_percent)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card className="mt-4">
                <CardHeader icon={Activity} title="Recent observations" description="Most recent 25 readings, newest first." />
                {recent.length === 0 ? (
                    <CardBody>
                        <p className="py-6 text-center text-[12px] text-muted">No observations have been loaded for this station.</p>
                    </CardBody>
                ) : (
                    <div className="eec-scrollbar max-h-[380px] overflow-auto">
                        <table className="w-full text-left">
                            <thead className="sticky top-0 bg-surface">
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">Measured at</th>
                                    <th className="px-5 py-2.5">Parameter</th>
                                    <th className="px-5 py-2.5 text-right">Value</th>
                                    <th className="px-5 py-2.5">Quality</th>
                                    <th className="px-5 py-2.5">Record type</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.map((row) => (
                                    <tr key={row.id} className="border-b border-line/70 last:border-0">
                                        <td className="px-5 py-2 text-[11.5px] text-muted">{formatDateTime(row.measured_at)}</td>
                                        <td className="px-5 py-2 text-[12px] text-ink">{row.parameter ?? '—'}</td>
                                        <td className="px-5 py-2 text-right text-[12px] tabular text-ink">
                                            {formatNumber(row.value, { maximumFractionDigits: 3 })} {row.unit ?? ''}
                                        </td>
                                        <td className="px-5 py-2">
                                            <Badge size="sm" tone={STATUS_TONES[row.quality_status] ?? 'neutral'}>{row.quality_status}</Badge>
                                        </td>
                                        <td className="px-5 py-2 text-[11.5px] text-muted">{row.record_type}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {restricted && (
                <p className="mt-4 text-[11px] text-amber-700 dark:text-amber-400">
                    This station holds {formatNumber(provisional)} provisional observations. Downloads are restricted until the
                    export restricted reports permission is granted.
                </p>
            )}

            <p className="mt-4 inline-flex items-center gap-1.5 text-[11px] text-muted">
                <Compass className="h-3 w-3" strokeWidth={2} />
                Coordinates are stored in WGS84 (EPSG:4326); the GeoJSON export carries the station point.
            </p>
        </AppLayout>
    );
}
