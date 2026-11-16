import { dataStatusLabel } from '@/Utils/basins';
import { cn } from '@/Utils/cn';
import { formatArea, formatCoordinate, formatDate, formatElevation, formatNumber } from '@/Utils/format';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BarChart2, CalendarClock, MapPin, RadioTower, Waves } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

function StatTile({ label, value }) {
    return (
        <div className="rounded-lg border border-line bg-canvas/50 px-2.5 py-2">
            <p className="text-[9.5px] font-semibold uppercase tracking-[0.1em] text-muted">{label}</p>
            <p className="mt-0.5 truncate text-[12.5px] font-medium text-ink">{value}</p>
        </div>
    );
}

function DetailRow({ label, children }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-line/70 py-1.5 last:border-0">
            <span className="shrink-0 text-[11px] text-muted">{label}</span>
            <span className="min-w-0 text-right text-[11.5px] font-medium text-ink">{children}</span>
        </div>
    );
}

function PanelLink({ href, children }) {
    return (
        <Link
            href={href}
            className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white transition-colors hover:bg-brand-800 focus-ring dark:bg-brand-600 dark:hover:bg-brand-500"
        >
            {children}
            <ArrowUpRight className="h-3.5 w-3.5" strokeWidth={2} />
        </Link>
    );
}

export function BasinInfoPanel({ basin }) {
    return (
        <div className="space-y-4">
            <div className="flex items-start gap-2.5">
                <span className="mt-1 h-3 w-3 shrink-0 rounded-sm" style={{ backgroundColor: basin.color }} />
                <div className="min-w-0">
                    <p className="text-sm font-semibold leading-tight text-ink">{basin.name}</p>
                    <p className="mt-0.5 font-mono text-[11px] text-muted">{basin.code}</p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
                <StatTile label="Area" value={formatArea(basin.area_km2)} />
                <StatTile label="Sub-basins" value={formatNumber(basin.sub_basins_count)} />
                <StatTile label="Stations" value={formatNumber(basin.stations_count)} />
                <StatTile label="Data status" value={dataStatusLabel(basin.data_status)} />
            </div>

            <div>
                <DetailRow label="Coordinates">
                    {basin.centroid_lat !== null && basin.centroid_lat !== undefined && basin.centroid_lng !== null && basin.centroid_lng !== undefined
                        ? `${formatCoordinate(basin.centroid_lat)}, ${formatCoordinate(basin.centroid_lng)}`
                        : 'Not recorded'}
                </DetailRow>
                <DetailRow label="Mean elevation">{formatElevation(basin.elevation_m)}</DetailRow>
                <DetailRow label="Alternative name">{basin.name_alt || 'Not recorded'}</DetailRow>
                <DetailRow label="Data source">{basin.data_source || 'Not recorded'}</DetailRow>
            </div>

            <p className="text-[10.5px] leading-relaxed text-muted">
                Sub-basin boundaries are drawn when a basin is selected. Hydrological readings stay off the map and load on demand.
            </p>

            <PanelLink href={basin.href}>Open full basin details</PanelLink>
        </div>
    );
}

export function SubBasinInfoPanel({ subBasin }) {
    return (
        <div className="space-y-4">
            <div className="flex items-start gap-2.5">
                <span className="mt-1 h-3 w-3 shrink-0 rounded-sm" style={{ backgroundColor: subBasin.color }} />
                <div className="min-w-0">
                    <p className="text-sm font-semibold leading-tight text-ink">{subBasin.name}</p>
                    <p className="mt-0.5 font-mono text-[11px] text-muted">{subBasin.code}</p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
                <StatTile label="Area" value={formatArea(subBasin.area_km2)} />
                <StatTile label="Mean elevation" value={formatElevation(subBasin.elevation_m)} />
                <StatTile label="Stations" value={formatNumber(subBasin.stations_count)} />
                <StatTile label="Data status" value={dataStatusLabel(subBasin.data_status)} />
            </div>

            <div>
                <DetailRow label="Parent basin">
                    {subBasin.basin ? `${subBasin.basin.code} — ${subBasin.basin.name}` : 'Not recorded'}
                </DetailRow>
                <DetailRow label="Coordinates">
                    {subBasin.centroid_lat !== null && subBasin.centroid_lat !== undefined && subBasin.centroid_lng !== null && subBasin.centroid_lng !== undefined
                        ? `${formatCoordinate(subBasin.centroid_lat)}, ${formatCoordinate(subBasin.centroid_lng)}`
                        : 'Not recorded'}
                </DetailRow>
                <DetailRow label="Alternative name">{subBasin.name_alt || 'Not recorded'}</DetailRow>
            </div>

            <PanelLink href={subBasin.href}>Open sub-basin details</PanelLink>
        </div>
    );
}

export function StationInfoPanel({ detail }) {
    const station = detail.station;
    const availability = detail.availability ?? { records: 0 };
    const parameters = detail.parameters ?? [];

    return (
        <div className="space-y-4">
            <div className="flex items-start gap-2.5">
                <span
                    className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full text-white"
                    style={{ backgroundColor: station.color }}
                >
                    <RadioTower className="h-4 w-4" strokeWidth={1.75} />
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold leading-tight text-ink">{station.name}</p>
                    <p className="mt-0.5 font-mono text-[11px] text-muted">{station.code}</p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-1.5">
                <span className="rounded-full px-2 py-0.5 text-[10px] font-medium text-white" style={{ backgroundColor: station.color }}>
                    {station.type_label}
                </span>
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-[10px] font-medium',
                        station.is_active
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                            : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                    )}
                >
                    {station.is_active ? 'Active' : 'Inactive'}
                </span>
            </div>

            <div>
                <DetailRow label="Coordinates">
                    {station.latitude === null
                        ? 'Not recorded'
                        : `${formatCoordinate(station.latitude)}, ${formatCoordinate(station.longitude)}`}
                </DetailRow>
                <DetailRow label="Parent basin">{station.basin ? `${station.basin.code} — ${station.basin.name}` : 'Not recorded'}</DetailRow>
                <DetailRow label="Sub-basin">{station.sub_basin ? station.sub_basin.name : 'Not assigned'}</DetailRow>
                <DetailRow label="Watershed">{station.watershed ? station.watershed.name : 'Not assigned'}</DetailRow>
                <DetailRow label="Elevation">{formatElevation(station.elevation_m)}</DetailRow>
                <DetailRow label="Operator">{station.operator || 'Not recorded'}</DetailRow>
                <DetailRow label="Data status">{dataStatusLabel(station.data_status)}</DetailRow>
            </div>

            <div>
                <div className="mb-1.5 flex items-center gap-1.5">
                    <Waves className="h-3.5 w-3.5 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                    <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Available parameters</p>
                </div>

                {parameters.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-line px-2.5 py-2 text-[11px] text-muted">
                        No parameter has been configured for this station yet.
                    </p>
                ) : (
                    <ul className="space-y-1">
                        {parameters.map((parameter) => (
                            <li key={parameter.id} className="flex items-center justify-between gap-2 rounded-md bg-canvas/60 px-2.5 py-1.5">
                                <span className="truncate text-[11.5px] text-ink">{parameter.name}</span>
                                <span className="shrink-0 text-[10.5px] text-muted">{parameter.unit || parameter.code}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {detail.series && detail.series.length > 0 && (
                <div className="rounded-lg border border-sky-200 bg-sky-50/40 p-2.5 dark:border-sky-900/40 dark:bg-sky-950/20">
                    <div className="flex items-center justify-between">
                        <p className="flex items-center gap-1.5 text-[10.5px] font-semibold uppercase tracking-[0.1em] text-sky-800 dark:text-sky-300">
                            <BarChart2 className="h-3 w-3" />
                            Monthly {detail.rainfallFilter?.parameter === 'temperature' ? 'Temp' : 'Rainfall'}
                        </p>
                        <span className="text-[10px] text-muted">
                            {detail.series.length} months
                        </span>
                    </div>
                    <div className="mt-2 h-32 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={detail.series} margin={{ top: 4, right: 4, bottom: 0, left: -20 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#cbd5e1" opacity={0.6} />
                                <XAxis dataKey="label" tick={{ fontSize: 9, fill: '#64748b' }} tickLine={false} axisLine={false} />
                                <YAxis tick={{ fontSize: 9, fill: '#64748b' }} tickLine={false} axisLine={false} />
                                <Tooltip
                                    formatter={(value) => [`${value} ${detail.rainfallFilter?.parameter === 'temperature' ? '°C' : 'mm'}`, 'Total']}
                                    labelStyle={{ fontSize: 11, fontWeight: 600, color: '#0f172a' }}
                                    contentStyle={{ borderRadius: 6, fontSize: 11, border: '1px solid #cbd5e1' }}
                                />
                                <Bar dataKey="total" fill="#0284c7" radius={[2, 2, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            )}

            <div className="rounded-lg border border-line bg-canvas/50 px-2.5 py-2">
                <div className="flex items-center gap-1.5">
                    <CalendarClock className="h-3.5 w-3.5 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                    <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Data availability</p>
                </div>

                {availability.records > 0 ? (
                    <p className="mt-1 text-[11.5px] leading-relaxed text-ink">
                        {formatNumber(availability.records)} records
                        {availability.first_at ? ` · ${formatDate(availability.first_at)} → ${formatDate(availability.last_at)}` : ''}
                    </p>
                ) : (
                    <p className="mt-1 text-[11px] leading-relaxed text-muted">
                        No observations stored for this station yet. Readings are loaded only when data exists.
                    </p>
                )}
            </div>

            {station.basin && (
                <Link
                    href={`/basins/${station.basin.id}`}
                    className="inline-flex items-center gap-1.5 text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                >
                    <MapPin className="h-3.5 w-3.5" strokeWidth={1.75} />
                    Open {station.basin.code} basin record
                </Link>
            )}
        </div>
    );
}
