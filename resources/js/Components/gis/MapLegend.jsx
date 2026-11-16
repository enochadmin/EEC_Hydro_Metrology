import { cn } from '@/Utils/cn';

/**
 * Floating legend. Basin colours come from the server palette so the legend
 * and the polygons can never disagree.
 */
export default function MapLegend({ basins = [], stationTypes = [], observationMode = null, className }) {
    const mapped = basins.filter((basin) => basin.has_boundary);
    const isObs = Boolean(observationMode?.active);

    const unit = observationMode?.parameterCode === 'temperature' ? '°C' : (observationMode?.parameterCode === 'streamflow' ? 'm³/s' : 'mm');
    const paramLabel = observationMode?.parameterCode === 'temperature' ? 'Temperature' : (observationMode?.parameterCode === 'streamflow' ? 'Streamflow' : 'Rainfall');

    return (
        <details open className={cn('group w-64 rounded-xl border border-line bg-surface/95 shadow-lg backdrop-blur', className)}>
            <summary className="cursor-pointer list-none px-3 py-2 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted flex items-center justify-between">
                <span>Legend</span>
                {isObs && (
                    <span className="rounded bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 px-1.5 py-0.2 text-[9px] font-medium tracking-normal">
                        {paramLabel} scale
                    </span>
                )}
            </summary>

            <div className="max-h-80 space-y-3 overflow-y-auto border-t border-line px-3 py-2.5">
                {isObs && (
                    <div className="rounded-lg bg-sky-50/60 p-2 border border-sky-100 dark:bg-sky-950/20 dark:border-sky-900/30">
                        <p className="text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-300">
                            {paramLabel} ({unit})
                        </p>
                        <p className="text-[9.5px] text-muted mt-0.5">
                            {observationMode.from} → {observationMode.to}
                        </p>
                        <div className="mt-2 space-y-1.5">
                            {[
                                { label: `< 20 ${unit}`, color: '#bae6fd', size: 10 },
                                { label: `20 – 50 ${unit}`, color: '#38bdf8', size: 13 },
                                { label: `50 – 150 ${unit}`, color: '#0284c7', size: 16 },
                                { label: `150 – 300 ${unit}`, color: '#1d4ed8', size: 19 },
                                { label: `> 300 ${unit}`, color: '#1e3a8a', size: 22 },
                            ].map((level, i) => (
                                <div key={i} className="flex items-center gap-2.5">
                                    <span
                                        className="shrink-0 rounded-full border border-white dark:border-slate-800 shadow-sm"
                                        style={{
                                            width: `${level.size}px`,
                                            height: `${level.size}px`,
                                            backgroundColor: level.color,
                                        }}
                                    />
                                    <span className="text-[11px] font-medium text-ink">{level.label}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                <div>
                    <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Basins</p>
                    {mapped.length === 0 ? (
                        <p className="mt-1 text-[10.5px] leading-relaxed text-muted">No basin polygons imported.</p>
                    ) : (
                        <ul className="mt-1 space-y-1">
                            {mapped.map((basin) => (
                                <li key={basin.id} className="flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-sm" style={{ backgroundColor: basin.color }} />
                                    <span className="truncate text-[11px] text-ink">{basin.name}</span>
                                    <span className="ml-auto font-mono text-[9.5px] text-muted">{basin.code}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div>
                    <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Stations</p>
                    <ul className="mt-1 space-y-1">
                        {stationTypes.map((type) => (
                            <li key={type.key} className="flex items-center gap-2">
                                <span className="h-2 w-2 shrink-0 rounded-full ring-2 ring-surface" style={{ backgroundColor: type.color }} />
                                <span className="truncate text-[11px] text-ink">{type.label}</span>
                                <span className="ml-auto text-[9.5px] tabular text-muted">{type.count}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </details>
    );
}
