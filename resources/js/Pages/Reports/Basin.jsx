import Badge from '@/Components/Badge';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import ChartCard from '@/Components/reports/ChartCard';
import CoverageTiles from '@/Components/reports/CoverageTiles';
import ExportMenu from '@/Components/reports/ExportMenu';
import TimeSeriesChart from '@/Components/reports/TimeSeriesChart';
import AppLayout from '@/Layouts/AppLayout';
import { formatArea, formatCoordinates, formatDate, formatElevation, formatNumber } from '@/Utils/format';
import { coverageTone, formatPercent } from '@/Utils/reports';
import { Head, Link } from '@inertiajs/react';
import { BarChart3, Compass, FileText, GitBranch, Layers, RadioTower } from 'lucide-react';

const STATUS_TONES = { verified: 'success', provisional: 'warning', pending: 'neutral' };

function Field({ label, value }) {
    return (
        <div className="border-b border-line/60 py-2 last:border-0">
            <dt className="text-[10.5px] font-medium uppercase tracking-[0.1em] text-muted">{label}</dt>
            <dd className="mt-0.5 text-[12.5px] text-ink">{value}</dd>
        </div>
    );
}

export default function BasinReport({ report, trend, abilities = {} }) {
    const { basin, geography, sub_basins: subBasins, stations, parameters, coverage, quality, counts } = report;

    const provisional = quality.pending + quality.flagged + quality.rejected;
    const restricted = provisional > 0 && !abilities.export_restricted;
    const exportFormats = ['csv', 'xlsx', 'pdf', 'geojson'];

    return (
        <AppLayout breadcrumbs={[{ label: 'Reports', href: '/reports' }, { label: basin.name }]}>
            <Head title={`${basin.name} basin report`} />

            <PageHeader
                title={basin.name}
                description={`Basin report · ${basin.code}`}
                actions={
                    <ExportMenu
                        baseUrl={`/reports/basins/${basin.id}/export`}
                        formats={exportFormats}
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
                    { label: 'Area', value: formatArea(basin.area_km2) },
                    { label: 'Sub-basins', value: formatNumber(counts.sub_basins) },
                    { label: 'Monitoring stations', value: formatNumber(counts.stations) },
                    { label: 'Parameters with data', value: formatNumber(counts.parameters) },
                ]}
            />

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader icon={FileText} title="Specifications" />
                    <CardBody>
                        <dl className="grid gap-x-6 sm:grid-cols-2">
                            <Field label="Code" value={basin.code} />
                            <Field label="Alternative name" value={basin.name_alt ?? '—'} />
                            <Field label="Area" value={formatArea(basin.area_km2)} />
                            <Field label="Mean elevation" value={formatElevation(basin.elevation_m)} />
                            <Field label="Record status" value={<Badge size="sm" tone={STATUS_TONES[basin.data_status] ?? 'neutral'}>{basin.data_status}</Badge>} />
                            <Field label="Data source" value={basin.source ?? '—'} />
                            <Field label="Last updated" value={formatDate(basin.updated_at)} />
                            <Field label="Centroid" value={formatCoordinates(basin.centroid_lat, basin.centroid_lng)} />
                        </dl>
                        {basin.description && <p className="mt-3 text-[12px] leading-relaxed text-muted">{basin.description}</p>}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader icon={Compass} title="Geographic information" />
                    <CardBody>
                        <dl className="grid gap-x-6 sm:grid-cols-2">
                            <Field label="Boundary stored" value={geography.has_boundary ? 'Yes' : 'No'} />
                            <Field label="Boundary name" value={geography.boundary_name ?? '—'} />
                            <Field label="Reference system" value={geography.boundary_srid ? `EPSG:${geography.boundary_srid}` : '—'} />
                            <Field label="Vertices" value={formatNumber(geography.boundary_vertices)} />
                            <Field
                                label="Bounding box"
                                value={
                                    geography.bounds
                                        ? `${geography.bounds.west}, ${geography.bounds.south} → ${geography.bounds.east}, ${geography.bounds.north}`
                                        : '—'
                                }
                            />
                            <Field label="Effective from" value={formatDate(geography.boundary_effective_from)} />
                        </dl>
                        {!geography.has_boundary && (
                            <p className="mt-3 text-[11.5px] leading-relaxed text-muted">
                                No polygon has been imported for this basin, so the GeoJSON export contains no boundary feature.
                            </p>
                        )}
                    </CardBody>
                </Card>
            </div>

            <div className="mt-4">
                <ChartCard
                    icon={BarChart3}
                    title="Monthly trend"
                    description={trend ? `${trend.profile.name} · ${trend.metric_label}${trend.profile.unit ? ` (${trend.profile.unit})` : ''}` : 'No parameter has observations in this basin yet.'}
                    empty={!trend || trend.points.length === 0}
                    emptyMessage="No approved observations are stored for this basin, so no trend can be drawn."
                >
                    {trend && (
                        <TimeSeriesChart points={trend.points} unit={trend.profile.unit} decimals={trend.profile.decimals} height={280} />
                    )}
                </ChartCard>
            </div>

            <Card className="mt-4">
                <CardHeader icon={GitBranch} title="Sub-basins" description={`${counts.sub_basins} registered`} />
                {subBasins.length === 0 ? (
                    <CardBody>
                        <p className="py-6 text-center text-[12px] text-muted">No sub-basins are registered under this basin.</p>
                    </CardBody>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">Code</th>
                                    <th className="px-5 py-2.5">Name</th>
                                    <th className="px-5 py-2.5 text-right">Area</th>
                                    <th className="px-5 py-2.5 text-right">Stations</th>
                                    <th className="px-5 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subBasins.map((row) => (
                                    <tr key={row.id} className="border-b border-line/70 last:border-0">
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{row.code}</td>
                                        <td className="px-5 py-2.5 text-[12.5px] font-medium text-ink">{row.name}</td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatArea(row.area_km2)}</td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{row.stations}</td>
                                        <td className="px-5 py-2.5">
                                            <Badge size="sm" tone={STATUS_TONES[row.data_status] ?? 'neutral'}>{row.data_status}</Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <Card className="mt-4">
                <CardHeader icon={RadioTower} title="Monitoring stations" description={`${counts.stations} registered`} />
                {stations.length === 0 ? (
                    <CardBody>
                        <p className="py-6 text-center text-[12px] text-muted">No monitoring stations are registered in this basin.</p>
                    </CardBody>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">Station</th>
                                    <th className="px-5 py-2.5">Code</th>
                                    <th className="px-5 py-2.5">Sub-basin</th>
                                    <th className="px-5 py-2.5 text-right">Parameters</th>
                                    <th className="px-5 py-2.5 text-right">Records</th>
                                    <th className="px-5 py-2.5">First / last record</th>
                                    <th className="px-5 py-2.5" />
                                </tr>
                            </thead>
                            <tbody>
                                {stations.map((station) => (
                                    <tr key={station.id} className="border-b border-line/70 last:border-0 hover:bg-canvas/60">
                                        <td className="px-5 py-2.5 text-[12.5px] font-medium text-ink">{station.name}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{station.code}</td>
                                        <td className="px-5 py-2.5 text-[12px] text-muted">{station.sub_basin ?? '—'}</td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{station.parameters}</td>
                                        <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatNumber(station.observations)}</td>
                                        <td className="px-5 py-2.5 text-[11.5px] text-muted">
                                            {station.first_at ? `${formatDate(station.first_at)} – ${formatDate(station.last_at)}` : 'No records'}
                                        </td>
                                        <td className="px-5 py-2.5 text-right">
                                            <Link
                                                href={`/reports/stations/${station.id}`}
                                                className="text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                                            >
                                                Report
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader icon={Layers} title="Parameters and data coverage" description="Aggregation follows each parameter's value type." />
                    {parameters.length === 0 ? (
                        <CardBody>
                            <p className="py-6 text-center text-[12px] text-muted">No observations have been loaded for this basin.</p>
                        </CardBody>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                        <th className="px-5 py-2.5">Parameter</th>
                                        <th className="px-5 py-2.5">Unit</th>
                                        <th className="px-5 py-2.5 text-right">Records</th>
                                        <th className="px-5 py-2.5 text-right">Stations</th>
                                        <th className="px-5 py-2.5">Span</th>
                                        <th className="px-5 py-2.5 text-right">Missing</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {parameters.map((row) => (
                                        <tr key={row.parameter_id} className="border-b border-line/70 last:border-0">
                                            <td className="px-5 py-2.5 text-[12.5px] font-medium text-ink">{row.name}</td>
                                            <td className="px-5 py-2.5 text-[12px] text-muted">{row.unit ?? '—'}</td>
                                            <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatNumber(row.records)}</td>
                                            <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{row.stations}</td>
                                            <td className="px-5 py-2.5 text-[11.5px] text-muted">
                                                {row.first_at ? `${formatDate(row.first_at)} – ${formatDate(row.last_at)}` : '—'}
                                            </td>
                                            <td className="px-5 py-2.5 text-right text-[12px] tabular text-muted">{formatPercent(row.missing_percent)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card>
                    <CardHeader icon={BarChart3} title="Data quality" />
                    <CardBody className="space-y-2.5">
                        {[
                            ['Total observations', formatNumber(quality.records)],
                            ['Approved', formatNumber(quality.approved)],
                            ['Pending review', formatNumber(quality.pending)],
                            ['Flagged', formatNumber(quality.flagged)],
                            ['Rejected', formatNumber(quality.rejected)],
                            ['Approved share', formatPercent(quality.approved_percent)],
                            ['Open quality issues', formatNumber(quality.open_issues)],
                            ['Coverage span', coverage.first_at ? `${formatDate(coverage.first_at)} – ${formatDate(coverage.last_at)}` : '—'],
                        ].map(([label, value]) => (
                            <div key={label} className="flex items-center justify-between gap-3">
                                <span className="text-[12px] text-muted">{label}</span>
                                <span className="text-[12px] font-medium tabular text-ink">{value}</span>
                            </div>
                        ))}
                        <Badge size="sm" tone={coverageTone(quality.approved_percent)}>
                            {quality.approved_percent === null ? 'No records' : `${formatPercent(quality.approved_percent)} approved`}
                        </Badge>
                    </CardBody>
                </Card>
            </div>

            {restricted && (
                <p className="mt-4 text-[11px] text-amber-700 dark:text-amber-400">
                    This basin holds {formatNumber(provisional)} provisional observations. Downloads are restricted until the
                    export restricted reports permission is granted.
                </p>
            )}
        </AppLayout>
    );
}
