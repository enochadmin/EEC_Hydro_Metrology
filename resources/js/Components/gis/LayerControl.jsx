import Button from '@/Components/Button';
import { cn } from '@/Utils/cn';
import { CloudRain, Info, Map as MapIcon, UploadCloud } from 'lucide-react';

const GROUPS = ['Boundaries', 'Monitoring', 'Reference', 'Raster'];

function LayerRow({ layer, checked, disabled, hint, onToggle }) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-2.5 rounded-lg px-2.5 py-2 transition-colors',
                disabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-canvas',
            )}
        >
            <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={() => onToggle(layer.key)}
                className="mt-0.5 rounded border-line text-brand-600 focus:ring-brand-500"
            />
            <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                    <span className="text-[12.5px] font-medium text-ink">{layer.label}</span>
                    <span className="shrink-0 rounded-full bg-canvas px-1.5 py-0.5 text-[10px] font-medium tabular text-muted">{layer.count}</span>
                </span>
                <span className="mt-0.5 block text-[10.5px] leading-relaxed text-muted">{hint ?? layer.description}</span>
            </span>
        </label>
    );
}

export default function LayerControl({
    layers = [],
    visible = {},
    onToggle,
    stationTypes = [],
    activeTypes = [],
    onToggleType,
    basemaps = [],
    basemap,
    basemapStatus = {},
    onBasemapChange,
    hasBasinSelection = false,
    canImport = false,
    onImport,
    rainfallFilter = null,
    onRainfallFilterChange,
    onApplyRainfallFilter,
    onClearRainfallFilter,
    className,
}) {
    const available = layers.filter((layer) => layer.available);
    const awaiting = layers.filter((layer) => !layer.available);
    const stationLayer = layers.find((layer) => layer.key === 'stations');

    return (
        <div className={cn('space-y-4', className)}>
            {/* Observation / Rainfall Date Filter */}
            <div className="rounded-xl border border-line bg-canvas/40 p-2.5">
                <div className="flex items-center justify-between gap-1">
                    <span className="flex items-center gap-1.5 text-[11px] font-semibold text-ink">
                        <CloudRain className="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" />
                        Rainfall & Date Filter
                    </span>
                    {rainfallFilter?.active && (
                        <span className="rounded-full bg-sky-100 px-1.5 py-0.5 text-[9.5px] font-medium text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
                            Active
                        </span>
                    )}
                </div>
                <p className="mt-1 text-[10px] leading-relaxed text-muted">
                    Filter monitoring stations by observation date range and render graduated rainfall markers on the map.
                </p>

                <div className="mt-2.5 space-y-2">
                    <div>
                        <label className="block text-[10px] font-medium text-muted uppercase">Parameter</label>
                        <select
                            value={rainfallFilter?.parameter ?? 'rainfall'}
                            onChange={(e) => onRainfallFilterChange?.({ parameter: e.target.value })}
                            className="mt-0.5 block w-full rounded-md border-line bg-surface py-1 px-2 text-[11.5px] text-ink focus:border-sky-500 focus:ring-sky-500"
                        >
                            <option value="rainfall">Rainfall (Precipitation, mm)</option>
                            <option value="temperature">Temperature (°C)</option>
                            <option value="streamflow">Streamflow (Discharge, m³/s)</option>
                            <option value="water_level">Water Level (m)</option>
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-1.5">
                        <div>
                            <label className="block text-[10px] font-medium text-muted uppercase">From</label>
                            <input
                                type="date"
                                value={rainfallFilter?.from ?? ''}
                                onChange={(e) => onRainfallFilterChange?.({ from: e.target.value })}
                                className="mt-0.5 block w-full rounded-md border-line bg-surface py-1 px-1.5 text-[11px] text-ink focus:border-sky-500 focus:ring-sky-500"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-medium text-muted uppercase">To</label>
                            <input
                                type="date"
                                value={rainfallFilter?.to ?? ''}
                                onChange={(e) => onRainfallFilterChange?.({ to: e.target.value })}
                                className="mt-0.5 block w-full rounded-md border-line bg-surface py-1 px-1.5 text-[11px] text-ink focus:border-sky-500 focus:ring-sky-500"
                            />
                        </div>
                    </div>

                    {/* Quick Presets */}
                    <div className="flex flex-wrap gap-1 pt-0.5">
                        <button
                            type="button"
                            onClick={() => onRainfallFilterChange?.({ from: '2022-01-01', to: '2022-12-31' })}
                            className="rounded bg-surface px-1.5 py-0.5 text-[9.5px] font-medium text-muted border border-line hover:text-ink hover:bg-canvas"
                        >
                            Year 2022
                        </button>
                        <button
                            type="button"
                            onClick={() => onRainfallFilterChange?.({ from: '2000-01-01', to: '2010-12-31' })}
                            className="rounded bg-surface px-1.5 py-0.5 text-[9.5px] font-medium text-muted border border-line hover:text-ink hover:bg-canvas"
                        >
                            2000–2010
                        </button>
                        <button
                            type="button"
                            onClick={() => onRainfallFilterChange?.({ from: '1973-01-01', to: '2022-12-31' })}
                            className="rounded bg-surface px-1.5 py-0.5 text-[9.5px] font-medium text-muted border border-line hover:text-ink hover:bg-canvas"
                        >
                            1973–2022
                        </button>
                    </div>

                    <div className="flex items-center gap-1.5 pt-1">
                        <button
                            type="button"
                            disabled={rainfallFilter?.loading}
                            onClick={onApplyRainfallFilter}
                            className="flex-1 rounded-md bg-sky-600 px-2.5 py-1.5 text-center text-[11.5px] font-medium text-white hover:bg-sky-700 disabled:opacity-50 transition-colors shadow-sm"
                        >
                            {rainfallFilter?.loading ? 'Filtering...' : 'Apply Date Filter'}
                        </button>
                        {rainfallFilter?.active && (
                            <button
                                type="button"
                                onClick={onClearRainfallFilter}
                                title="Clear filter"
                                className="rounded-md border border-line bg-surface px-2 py-1.5 text-[11px] font-medium text-muted hover:text-ink hover:bg-canvas transition-colors"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    {rainfallFilter?.meta && rainfallFilter.active && (
                        <p className="pt-1 text-[10px] text-sky-700 dark:text-sky-300 font-medium">
                            ✓ {rainfallFilter.meta.count} stations with data · Max: {Number(rainfallFilter.meta.max_value).toLocaleString()} {rainfallFilter.parameter === 'temperature' ? '°C' : 'mm'}
                        </p>
                    )}
                </div>
            </div>

            <div>
                <div className="flex items-center justify-between gap-2">
                    <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Basemap</p>
                </div>
                <select
                    value={basemap}
                    onChange={(event) => onBasemapChange(event.target.value)}
                    className="mt-1.5 block w-full rounded-lg border-line bg-surface py-2 pl-3 pr-8 text-[12.5px] text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500"
                >
                    {basemaps.map((option) => (
                        <option key={option.id} value={option.id}>{option.label}</option>
                    ))}
                </select>

                {basemapStatus.message && (
                    <p className="mt-1.5 flex items-start gap-1.5 text-[10px] leading-relaxed text-muted">
                        <Info className="mt-0.5 h-3 w-3 shrink-0" strokeWidth={2} />
                        <span>{basemapStatus.message}</span>
                    </p>
                )}
            </div>

            <div>
                <p className="px-2.5 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Layers</p>

                <div className="mt-1 space-y-3">
                    {GROUPS.map((group) => {
                        const items = available.filter((layer) => layer.group === group);

                        if (items.length === 0) {
                            return null;
                        }

                        return (
                            <div key={group}>
                                <p className="px-2.5 pb-0.5 text-[10px] font-medium uppercase tracking-wide text-muted/80">{group}</p>
                                <div className="space-y-0.5">
                                    {items.map((layer) => {
                                        const needsBasin = layer.key === 'sub_basins';
                                        const disabled = needsBasin && !hasBasinSelection;

                                        return (
                                            <LayerRow
                                                key={layer.key}
                                                layer={layer}
                                                checked={Boolean(visible[layer.key])}
                                                disabled={disabled}
                                                hint={needsBasin && !hasBasinSelection ? 'Select a basin to enable sub-basin boundaries.' : undefined}
                                                onToggle={onToggle}
                                            />
                                        );
                                    })}
                                </div>

                                {group === 'Monitoring' && stationLayer?.available && visible.stations && (
                                    <div className="mt-2 space-y-1 rounded-lg border border-line bg-canvas/40 p-2">
                                        <p className="px-1 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted">Station types</p>
                                        {stationTypes.map((type) => {
                                            const active = activeTypes.includes(type.key);

                                            return (
                                                <button
                                                    key={type.key}
                                                    type="button"
                                                    onClick={() => onToggleType(type.key)}
                                                    className={cn(
                                                        'flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left transition-colors',
                                                        active ? 'bg-surface shadow-sm' : 'opacity-60 hover:opacity-100',
                                                    )}
                                                    aria-pressed={active}
                                                >
                                                    <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: type.color }} />
                                                    <span className="min-w-0 flex-1 truncate text-[11.5px] text-ink">{type.label}</span>
                                                    <span className="text-[10px] tabular text-muted">{type.count}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>

                {available.length === 0 && (
                    <p className="mx-2.5 mt-2 rounded-lg border border-dashed border-line px-2.5 py-3 text-[11.5px] leading-relaxed text-muted">
                        No geographic datasets are available yet.
                    </p>
                )}
            </div>

            {awaiting.length > 0 && (
                <details className="rounded-lg border border-line bg-canvas/40 px-2.5 py-2">
                    <summary className="cursor-pointer text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">
                        Awaiting datasets ({awaiting.length})
                    </summary>
                    <ul className="mt-2 space-y-1.5">
                        {awaiting.map((layer) => (
                            <li key={layer.key} className="text-[10.5px] leading-relaxed text-muted">
                                <span className="font-medium text-ink">{layer.label}</span> — {layer.reason}
                            </li>
                        ))}
                    </ul>
                </details>
            )}

            {canImport && (
                <div className="space-y-2 border-t border-line pt-3">
                    <Button variant="secondary" size="sm" className="w-full" onClick={onImport}>
                        <UploadCloud className="h-3.5 w-3.5" strokeWidth={1.75} />
                        Import GeoJSON layer
                    </Button>
                    <p className="px-1 text-[10px] leading-relaxed text-muted">
                        National boundary, rivers and lakes. EPSG:4326 only; geometry type is checked per layer.
                    </p>
                </div>
            )}

            <div className="flex items-start gap-2 rounded-lg border border-line bg-canvas/40 px-2.5 py-2">
                <MapIcon className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                <p className="text-[10px] leading-relaxed text-muted">
                    Layers appear only when a dataset exists. Missing rasters are never drawn as empty tiles.
                </p>
            </div>
        </div>
    );
}
