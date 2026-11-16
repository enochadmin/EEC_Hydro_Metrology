import { cn } from '@/Utils/cn';
import { divIcon } from 'leaflet';
import { LocateFixed, Map, Mountain, Satellite, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CircleMarker, MapContainer, Marker, TileLayer, Tooltip, useMap, ZoomControl } from 'react-leaflet';

const BASEMAPS = [
    {
        id: 'terrain',
        label: 'Terrain',
        icon: Mountain,
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
        attribution: 'Tiles &copy; Esri, HERE, Garmin, USGS, &copy; OpenStreetMap contributors',
        max_zoom: 19,
    },
    {
        id: 'imagery',
        label: 'Satellite',
        icon: Satellite,
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        attribution: 'Tiles &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community',
        max_zoom: 19,
    },
    {
        id: 'streets',
        label: 'Streets',
        icon: Map,
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; OpenStreetMap contributors',
        max_zoom: 19,
    },
];

/**
 * Schematic positions for the twelve major basins. These are reference points
 * for the public overview, not the surveyed boundaries held in the registry.
 */
const BASINS = [
    { name: 'Tekeze', region: 'Northern highlands', center: [13.3, 38.2], radius: 24, color: '#1d4ed8' },
    { name: 'Mereb', region: 'Northern highlands', center: [14.6, 38.4], radius: 17, color: '#2563eb', labelDir: 'right' },
    { name: 'Danakil', region: 'North-eastern lowlands', center: [13.2, 40.6], radius: 22, color: '#b45309' },
    { name: 'Abbay', region: 'North-western highlands', center: [10.9, 37.2], radius: 30, color: '#1f6076' },
    { name: 'Aysha', region: 'Eastern lowlands', center: [10.6, 42.4], radius: 18, color: '#a16207' },
    { name: 'Awash', region: 'Central Rift Valley', center: [9.0, 40.2], radius: 26, color: '#0e7490' },
    { name: 'Baro Akobo', region: 'South-western lowlands', center: [7.9, 34.6], radius: 25, color: '#0369a1' },
    { name: 'Omo Gibe', region: 'South-western highlands', center: [7.2, 36.9], radius: 24, color: '#0891b2' },
    { name: 'Rift Valley', region: 'Central Rift Valley', center: [7.9, 38.6], radius: 23, color: '#0f766e' },
    { name: 'Genale Dawa', region: 'South-eastern highlands', center: [5.6, 41.2], radius: 21, color: '#4d7c0f' },
    { name: 'Wabi Shebelle', region: 'South-eastern lowlands', center: [6.6, 43.4], radius: 23, color: '#7c6a2a' },
    { name: 'Ogaden', region: 'Eastern lowlands', center: [6.7, 45.3], radius: 27, color: '#475569' },
];

/** Representative gauge positions — type only, no observation data is public. */
const GAUGES = [
    { position: [11.6, 37.4], type: 'River gauge' },
    { position: [9.05, 40.15], type: 'River gauge' },
    { position: [8.15, 37.6], type: 'Rain gauge' },
    { position: [7.0, 37.9], type: 'Water quality' },
    { position: [13.35, 38.4], type: 'River gauge' },
];

const HOME_VIEW = { center: [9.145, 40.4897], zoom: 6 };

function FlyToBasin({ basin }) {
    const map = useMap();

    useEffect(() => {
        if (basin) {
            map.flyTo(basin.center, 7, { duration: 0.9 });
        }
    }, [basin, map]);

    return null;
}

function ResetView({ signal }) {
    const map = useMap();

    useEffect(() => {
        if (signal > 0) {
            map.flyTo(HOME_VIEW.center, HOME_VIEW.zoom, { duration: 0.8 });
        }
    }, [signal, map]);

    return null;
}

function BasemapChips({ active, onChange }) {
    return (
        <div
            role="group"
            aria-label="Basemap"
            className="pointer-events-auto flex items-center gap-0.5 rounded-lg border border-line/80 bg-surface/95 p-1 shadow-lg backdrop-blur"
        >
            {BASEMAPS.map((basemap) => {
                const Icon = basemap.icon;
                const selected = basemap.id === active;

                return (
                    <button
                        key={basemap.id}
                        type="button"
                        onClick={() => onChange(basemap.id)}
                        aria-pressed={selected}
                        title={basemap.label}
                        className={cn(
                            'focus-ring inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-[11.5px] font-medium transition-colors',
                            selected ? 'bg-brand-700 text-white shadow-sm' : 'text-muted hover:bg-canvas hover:text-ink',
                        )}
                    >
                        <Icon className="h-3.5 w-3.5" strokeWidth={1.9} />
                        <span className="hidden sm:inline">{basemap.label}</span>
                    </button>
                );
            })}
        </div>
    );
}

function LayerToggle({ label, color, checked, onChange }) {
    return (
        <button
            type="button"
            onClick={() => onChange(!checked)}
            aria-pressed={checked}
            className={cn(
                'focus-ring inline-flex h-8 items-center gap-2 rounded-md border px-2.5 text-[11.5px] font-medium transition-colors',
                checked ? 'border-line bg-surface text-ink shadow-sm' : 'border-transparent bg-surface/70 text-muted hover:text-ink',
            )}
        >
            <span className="h-2 w-2 rounded-full transition-opacity" style={{ backgroundColor: color, opacity: checked ? 1 : 0.35 }} />
            {label}
        </button>
    );
}

export default function WelcomeMap({ className }) {
    const [basemapId, setBasemapId] = useState('terrain');
    const [selected, setSelected] = useState(null);
    const [showBasins, setShowBasins] = useState(true);
    const [showGauges, setShowGauges] = useState(true);
    const [resetSignal, setResetSignal] = useState(0);

    const basemap = BASEMAPS.find((item) => item.id === basemapId) ?? BASEMAPS[0];

    const gaugeIcon = useMemo(
        () =>
            divIcon({
                className: 'welcome-gauge-wrap',
                html: '<span class="welcome-gauge"><span class="welcome-gauge-ring"></span></span>',
                iconSize: [16, 16],
                iconAnchor: [8, 8],
            }),
        [],
    );

    return (
        <div className={cn('eec-map-shell relative overflow-hidden rounded-2xl border border-line bg-surface shadow-lift', className)}>
            <div className="h-[400px] sm:h-[480px] lg:h-[540px]">
                <MapContainer
                    center={HOME_VIEW.center}
                    zoom={HOME_VIEW.zoom}
                    minZoom={4}
                    maxZoom={13}
                    zoomControl={false}
                    scrollWheelZoom={false}
                    className="h-full w-full"
                >
                    <TileLayer key={basemap.id} url={basemap.url} attribution={basemap.attribution} maxZoom={basemap.max_zoom} />

                    <ZoomControl position="bottomright" />
                    <FlyToBasin basin={selected} />
                    <ResetView signal={resetSignal} />

                    {showBasins &&
                        BASINS.map((basin) => {
                            const isSelected = selected?.name === basin.name;

                            return (
                                <CircleMarker
                                    key={basin.name}
                                    center={basin.center}
                                    radius={isSelected ? basin.radius + 4 : basin.radius}
                                    pathOptions={{
                                        color: basin.color,
                                        weight: isSelected ? 2.4 : 1.6,
                                        opacity: 0.95,
                                        fillColor: basin.color,
                                        fillOpacity: isSelected ? 0.42 : 0.18,
                                    }}
                                    eventHandlers={{
                                        click: () => setSelected(basin),
                                        mouseover: (event) => event.target.setStyle({ fillOpacity: 0.4, weight: 2.2 }),
                                        mouseout: (event) =>
                                            event.target.setStyle({
                                                fillOpacity: isSelected ? 0.42 : 0.18,
                                                weight: isSelected ? 2.4 : 1.6,
                                            }),
                                    }}
                                >
                                    <Tooltip
                                        permanent
                                        direction={basin.labelDir ?? 'top'}
                                        offset={basin.labelDir === 'right' ? [basin.radius + 4, 0] : [0, -(basin.radius + 3)]}
                                        opacity={0.92}
                                        className="eec-map-tooltip welcome-basin-label"
                                    >
                                        {basin.name}
                                    </Tooltip>
                                </CircleMarker>
                            );
                        })}

                    {showBasins && selected && (
                        <CircleMarker
                            center={selected.center}
                            radius={selected.radius + 10}
                            pathOptions={{
                                color: selected.color,
                                weight: 1.2,
                                opacity: 0.65,
                                fill: false,
                                className: 'welcome-orbit',
                            }}
                            interactive={false}
                        />
                    )}

                    {showGauges &&
                        GAUGES.map((gauge, index) => (
                            <Marker key={index} position={gauge.position} icon={gaugeIcon} keyboard={false}>
                                <Tooltip direction="top" offset={[0, -10]} className="eec-map-tooltip">
                                    <span className="font-semibold">{gauge.type}</span>
                                    <span className="text-muted"> · monitoring point</span>
                                </Tooltip>
                            </Marker>
                        ))}
                </MapContainer>
            </div>

            <div className="pointer-events-none absolute inset-0 z-[800]">
                <div className="pointer-events-auto absolute left-3 top-3 hidden max-w-[230px] rounded-lg border border-line/80 bg-surface/95 px-3 py-2.5 shadow-lg backdrop-blur sm:block">
                    <p className="flex items-center gap-1.5 text-[9.5px] font-semibold uppercase tracking-[0.16em] text-brand-700 dark:text-brand-300">
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-500 opacity-60" />
                            <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-brand-600" />
                        </span>
                        Basin network
                    </p>
                    <p className="mt-1.5 text-[11.5px] leading-5 text-muted">
                        Select a basin to explore the registry. Boundaries shown are schematic.
                    </p>
                </div>

                <div className="absolute right-3 top-3 flex items-center gap-1.5">
                    <BasemapChips active={basemapId} onChange={setBasemapId} />
                    <button
                        type="button"
                        onClick={() => {
                            setSelected(null);
                            setResetSignal((value) => value + 1);
                        }}
                        title="Reset view"
                        className="focus-ring pointer-events-auto grid h-10 w-10 place-items-center rounded-lg border border-line/80 bg-surface/95 text-muted shadow-lg backdrop-blur transition-colors hover:text-ink"
                    >
                        <LocateFixed className="h-4 w-4" strokeWidth={1.8} />
                    </button>
                </div>

                <div className="absolute inset-x-3 bottom-3 flex items-end justify-between gap-2 sm:inset-x-auto sm:left-3 sm:w-auto">
                    <div className="flex flex-col gap-2">
                        {selected && (
                            <div className="pointer-events-auto w-[min(240px,calc(100vw-6rem))] animate-pop-in rounded-lg border border-line bg-surface/95 p-3 shadow-xl backdrop-blur">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="flex items-center gap-1.5 text-[9.5px] font-semibold uppercase tracking-[0.16em] text-muted">
                                            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: selected.color }} />
                                            Major basin
                                        </p>
                                        <p className="mt-1 text-[13px] font-semibold text-ink">{selected.name}</p>
                                        <p className="text-[11px] text-muted">{selected.region}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setSelected(null)}
                                        aria-label="Clear selection"
                                        className="focus-ring rounded p-1 text-muted transition-colors hover:text-ink"
                                    >
                                        <X className="h-3.5 w-3.5" strokeWidth={2} />
                                    </button>
                                </div>
                                <p className="mt-2 border-t border-line pt-2 text-[10.5px] leading-4 text-muted">
                                    Sub-basin and gauge detail is available after sign-in.
                                </p>
                            </div>
                        )}

                        <div className="pointer-events-auto flex flex-wrap items-center gap-1 rounded-lg border border-line/80 bg-surface/90 p-1 shadow-lg backdrop-blur">
                            <LayerToggle label="Basins" color="#1f6076" checked={showBasins} onChange={setShowBasins} />
                            <LayerToggle label="Gauges" color="#f59e0b" checked={showGauges} onChange={setShowGauges} />
                        </div>
                    </div>

                    <div className="pointer-events-none hidden items-center gap-3 rounded-lg border border-line/80 bg-surface/90 px-2.5 py-1.5 text-[10px] text-muted shadow-lg backdrop-blur sm:flex">
                        <span className="flex items-center gap-1.5">
                            <span className="h-2.5 w-2.5 rounded-full border border-white bg-brand-700" />
                            Basin
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="h-2 w-2 rounded-full bg-amber-500 ring-2 ring-amber-500/30" />
                            Gauge
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
