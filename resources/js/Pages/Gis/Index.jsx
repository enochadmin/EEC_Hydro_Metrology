import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import MapCanvas from '@/Components/gis/MapCanvas';
import { FALLBACK_BASEMAP } from '@/Components/gis/basemaps';
import ImportGeoJsonDialog from '@/Components/gis/ImportGeoJsonDialog';
import LayerControl from '@/Components/gis/LayerControl';
import MapLegend from '@/Components/gis/MapLegend';
import MapSearch from '@/Components/gis/MapSearch';
import { BasinInfoPanel, StationInfoPanel, SubBasinInfoPanel } from '@/Components/gis/InfoPanels';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { Layers, Maximize2, Minimize2, RotateCcw, UploadCloud, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const ETHIOPIA_BOUNDS = [
    [3.3, 32.9],
    [14.9, 48.0],
];

const FEATURE_LAYERS = ['national_boundary', 'rivers', 'lakes', 'watersheds'];

async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json();
}

function bboxToBounds(bbox) {
    if (!bbox) {
        return null;
    }

    return [
        [Number(bbox.min_lat), Number(bbox.min_lng)],
        [Number(bbox.max_lat), Number(bbox.max_lng)],
    ];
}

export default function Index({
    layers = [],
    station_types = [],
    basin_legend = [],
    has_geometry = false,
    counts = {},
    importable_layers = [],
    initial = {},
    map = {},
    abilities = {},
    basemaps = [],
    basemap_status = {},
    default_basemap = null,
}) {
    const available = useMemo(
        () => Object.fromEntries(layers.map((layer) => [layer.key, Boolean(layer.available)])),
        [layers],
    );

    const [visible, setVisible] = useState(() => ({
        basins: Boolean(available.basins),
        stations: Boolean(available.stations),
        sub_basins: false,
        national_boundary: false,
        rivers: false,
        lakes: false,
        watersheds: false,
    }));
    const [basemap, setBasemap] = useState(() => default_basemap ?? basemaps[0]?.id ?? FALLBACK_BASEMAP.id);
    const [basins, setBasins] = useState(null);
    const [stations, setStations] = useState(null);
    const [subBasins, setSubBasins] = useState(null);
    const [layerData, setLayerData] = useState({});
    const [activeTypes, setActiveTypes] = useState(() => station_types.map((type) => type.key));
    const [selected, setSelected] = useState(null);
    const [selectedBasinId, setSelectedBasinId] = useState(null);
    const [selectedSubBasinId, setSelectedSubBasinId] = useState(null);
    const [fit, setFit] = useState(null);
    const [focus, setFocus] = useState(null);
    const [panelOpen, setPanelOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [layersOpen, setLayersOpen] = useState(false);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [resizeSignal, setResizeSignal] = useState(0);

    const [rainfallFilter, setRainfallFilter] = useState(() => ({
        active: Boolean(initial.from && initial.to),
        from: initial.from ?? '1973-01-01',
        to: initial.to ?? '2022-12-31',
        date: initial.date ?? '',
        parameter: initial.parameter ?? 'rainfall',
        loading: false,
        meta: null,
    }));
    const rainfallFilterRef = useRef(rainfallFilter);
    rainfallFilterRef.current = rainfallFilter;

    const shellRef = useRef(null);
    const bootstrapped = useRef(false);

    const basinLayerAvailable = Boolean(available.basins);
    const stationLayerAvailable = Boolean(available.stations);

    const loadBasins = useCallback(async () => {
        if (!basinLayerAvailable) {
            return;
        }

        try {
            setBasins(await getJson('/gis/data/basins?tolerance=0.01'));
        } catch {
            setBasins({ type: 'FeatureCollection', features: [] });
        }
    }, [basinLayerAvailable]);

    const loadStations = useCallback(async () => {
        if (!stationLayerAvailable) {
            return;
        }

        try {
            setStations(await getJson('/gis/data/stations'));
        } catch {
            setStations({ type: 'FeatureCollection', features: [] });
        }
    }, [stationLayerAvailable]);

    useEffect(() => {
        loadBasins();
    }, [loadBasins, counts.basins]);

    useEffect(() => {
        loadStations();
    }, [loadStations, counts.stations]);

    useEffect(() => {
        setLayerData({});
    }, [counts.national_boundary, counts.rivers, counts.lakes, counts.watersheds]);

    useEffect(() => {
        const pending = FEATURE_LAYERS.filter((key) => visible[key] && available[key] && !layerData[key]);

        if (pending.length === 0) {
            return;
        }

        let cancelled = false;

        pending.forEach(async (key) => {
            try {
                const url = key === 'watersheds'
                    ? '/gis/data/watersheds?tolerance=0.01'
                    : `/gis/data/features/${key}?tolerance=0.01`;

                const data = await getJson(url);

                if (!cancelled) {
                    setLayerData((previous) => ({ ...previous, [key]: data }));
                }
            } catch {
                // A layer that fails to load simply stays empty; the map remains usable.
            }
        });

        return () => {
            cancelled = true;
        };
    }, [visible, layerData, available]);

    const loadBasinContext = useCallback(async (id) => {
        const detail = await getJson(`/gis/data/basins/${id}?tolerance=0.0015`);

        setSelectedBasinId(id);
        setSubBasins(detail.sub_basins);
        setVisible((previous) => ({ ...previous, sub_basins: true }));

        return detail;
    }, []);

    const selectBasin = useCallback(async (id) => {
        if (!id) {
            return;
        }

        try {
            const detail = await loadBasinContext(id);

            setSelected({ type: 'basin', data: detail.basin });
            setSelectedSubBasinId(null);
            setPanelOpen(true);

            if (detail.basin.bbox) {
                setFit({ bounds: bboxToBounds(detail.basin.bbox), key: Date.now() });
            }
        } catch {
            // Keep the map interactive if a detail request fails.
        }
    }, [loadBasinContext]);

    const selectSubBasin = useCallback(async (id) => {
        if (!id) {
            return;
        }

        try {
            const detail = await getJson(`/gis/data/sub-basins/${id}?tolerance=0.0015`);

            setSelected({ type: 'sub_basin', data: detail.sub_basin });
            setSelectedSubBasinId(id);
            setPanelOpen(true);

            if (detail.sub_basin.bbox) {
                setFit({ bounds: bboxToBounds(detail.sub_basin.bbox), key: Date.now() });
            }

            if (detail.sub_basin.basin?.id) {
                await loadBasinContext(detail.sub_basin.basin.id);
            }
        } catch {
            // Ignore a failed detail request.
        }
    }, [loadBasinContext]);

    const handleRainfallFilterChange = useCallback((updates) => {
        setRainfallFilter((prev) => ({ ...prev, ...updates }));
    }, []);

    const applyRainfallFilter = useCallback(async (customFilter) => {
        const base = customFilter ?? rainfallFilterRef.current;
        // A single-day selection narrows the range to that day, so the layer
        // renders one value per station instead of a period total.
        const target = base.date ? { ...base, from: base.date, to: base.date } : base;
        if (!target.from || !target.to) {
            return;
        }

        setRainfallFilter((prev) => ({ ...prev, loading: true }));

        try {
            const url = `/gis/data/stations/rainfall?from=${target.from}&to=${target.to}&parameter=${target.parameter ?? 'rainfall'}`;
            const data = await getJson(url);

            setStations(data);
            setVisible((prev) => ({ ...prev, stations: true }));
            setRainfallFilter((prev) => ({
                ...prev,
                ...target,
                active: true,
                loading: false,
                meta: data.meta,
            }));
        } catch (error) {
            console.error('Failed to load observation filter:', error);
            setRainfallFilter((prev) => ({ ...prev, loading: false }));
        }
    }, []);

    const clearRainfallFilter = useCallback(async () => {
        setRainfallFilter((prev) => ({ ...prev, active: false, loading: false, meta: null }));
        await loadStations();
    }, [loadStations]);

    const selectStation = useCallback(async (id) => {
        if (!id) {
            return;
        }

        try {
            const activeFilter = rainfallFilterRef.current;
            const dayQuery = activeFilter.date ? `&date=${activeFilter.date}` : '';
            const detail = await getJson(`/gis/data/stations/${id}?parameter=${activeFilter.parameter}${dayQuery}`);
            const station = detail.station;

            if (rainfallFilterRef.current.active) {
                try {
                    const seriesData = await getJson(
                        `/gis/data/stations/${id}/series?from=${rainfallFilterRef.current.from}&to=${rainfallFilterRef.current.to}&parameter=${rainfallFilterRef.current.parameter}`
                    );
                    detail.series = seriesData.series;
                    detail.rainfallFilter = rainfallFilterRef.current;
                } catch {
                    // Series chart optional
                }
            }

            setSelected({ type: 'station', data: detail });
            setPanelOpen(true);

            if (station.latitude !== null && station.longitude !== null) {
                setFocus({ lat: Number(station.latitude), lng: Number(station.longitude), zoom: 12, key: Date.now() });
            }

            if (station.basin?.id) {
                await loadBasinContext(station.basin.id);
            }
        } catch {
            // Ignore a failed detail request.
        }
    }, [loadBasinContext]);

    useEffect(() => {
        if (bootstrapped.current) {
            return;
        }

        bootstrapped.current = true;

        if (initial.from && initial.to) {
            applyRainfallFilter({
                from: initial.from,
                to: initial.to,
                parameter: initial.parameter ?? 'rainfall',
            });
        }

        if (initial.basin) {
            selectBasin(initial.basin);
        } else if (initial.sub_basin) {
            selectSubBasin(initial.sub_basin);
        } else if (initial.station) {
            selectStation(initial.station);
        }
    }, [initial, selectBasin, selectSubBasin, selectStation, applyRainfallFilter]);

    useEffect(() => {
        const handler = () => {
            setIsFullscreen(Boolean(document.fullscreenElement));
            setResizeSignal(Date.now());
        };

        document.addEventListener('fullscreenchange', handler);

        return () => document.removeEventListener('fullscreenchange', handler);
    }, []);

    const toggleFullscreen = async () => {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else if (shellRef.current?.requestFullscreen) {
                await shellRef.current.requestFullscreen();
            }
        } catch {
            // Some embedded browsers reject fullscreen; the button then does nothing.
        }
    };

    const resetView = () => {
        setFit({ bounds: ETHIOPIA_BOUNDS, key: Date.now() });
        setSelected(null);
        setPanelOpen(false);
        setSelectedBasinId(null);
        setSelectedSubBasinId(null);
        setSubBasins(null);
        setVisible((previous) => ({ ...previous, sub_basins: false }));
    };

    const toggleLayer = (key) => {
        if (key === 'sub_basins' && !selectedBasinId) {
            return;
        }

        setVisible((previous) => ({ ...previous, [key]: !previous[key] }));
    };

    const toggleStationType = (key) => {
        setActiveTypes((previous) => (
            previous.includes(key) ? previous.filter((item) => item !== key) : [...previous, key]
        ));
    };

    const filteredStations = useMemo(() => {
        if (!stations) {
            return null;
        }

        return {
            ...stations,
            features: (stations.features ?? []).filter((feature) => (
                !feature.properties.station_type || activeTypes.includes(feature.properties.station_type)
            )),
        };
    }, [stations, activeTypes]);

    const handleSearchSelect = (result) => {
        if (result.type === 'basin') {
            selectBasin(result.id);
        } else if (result.type === 'sub_basin') {
            selectSubBasin(result.id);
        } else if (result.type === 'station') {
            selectStation(result.id);
        } else if (result.type === 'feature' && result.layer) {
            setVisible((previous) => ({ ...previous, [result.layer]: true }));

            if (result.lat !== null && result.lng !== null) {
                setFocus({ lat: Number(result.lat), lng: Number(result.lng), zoom: 7, key: Date.now() });
            }
        }
    };

    const mappedBasins = basin_legend.filter((basin) => basin.has_boundary).length;
    const basemapOptions = basemaps.length > 0 ? basemaps : [FALLBACK_BASEMAP];
    const currentBasemap = basemapOptions.find((option) => option.id === basemap) ?? basemapOptions[0];
    const isEmpty = !has_geometry && (counts.stations ?? 0) === 0;

    return (
        <AppLayout breadcrumbs={[{ label: 'Geospatial' }, { label: 'GIS map' }]}>
            <Head title="GIS map" />

            <PageHeader
                eyebrow="Geospatial"
                title="Interactive basin map"
                description="Basins, sub-basins, stations and reference layers."
                actions={
                    <>
                        <Badge tone="neutral" size="sm">
                            {mappedBasins}/{basin_legend.length} basins mapped · {counts.stations ?? 0} stations
                        </Badge>
                        {abilities.import && (
                            <Button size="sm" onClick={() => setImportOpen(true)}>
                                <UploadCloud className="h-4 w-4" strokeWidth={1.75} />
                                Import GeoJSON
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mt-5 flex gap-3">
                <aside className="hidden w-[286px] shrink-0 lg:block">
                    <Card className="eec-scrollbar max-h-[calc(100vh-13rem)] overflow-y-auto p-3">
                        <LayerControl
                            layers={layers}
                            visible={visible}
                            onToggle={toggleLayer}
                            stationTypes={station_types}
                            activeTypes={activeTypes}
                            onToggleType={toggleStationType}
                            basemaps={basemapOptions}
                            basemap={basemap}
                            basemapStatus={basemap_status}
                            onBasemapChange={setBasemap}
                            hasBasinSelection={Boolean(selectedBasinId)}
                            canImport={abilities.import}
                            onImport={() => setImportOpen(true)}
                            rainfallFilter={rainfallFilter}
                            onRainfallFilterChange={handleRainfallFilterChange}
                            onApplyRainfallFilter={() => applyRainfallFilter()}
                            onClearRainfallFilter={clearRainfallFilter}
                        />
                    </Card>
                </aside>

                <div
                    ref={shellRef}
                    className="eec-map-shell relative h-[calc(100vh-13rem)] min-h-[580px] flex-1 overflow-hidden rounded-xl border border-line bg-surface"
                >
                    <MapCanvas
                        center={map.center}
                        zoom={map.zoom}
                        minZoom={map.min_zoom}
                        maxZoom={map.max_zoom}
                        basemap={currentBasemap}
                        basins={available.basins ? basins : null}
                        subBasins={subBasins}
                        stations={filteredStations}
                        visibleLayers={visible}
                        selectedBasinId={selectedBasinId}
                        selectedSubBasinId={selectedSubBasinId}
                        nationalBoundary={layerData.national_boundary}
                        rivers={layerData.rivers}
                        lakes={layerData.lakes}
                        watersheds={layerData.watersheds}
                        onBasinSelect={(properties) => selectBasin(properties.id)}
                        onSubBasinSelect={(properties) => selectSubBasin(properties.id)}
                        onStationSelect={(properties) => selectStation(properties.id)}
                        fit={fit}
                        focus={focus}
                        resizeSignal={resizeSignal}
                    />

                    <MapSearch onSelect={handleSearchSelect} className="absolute left-3 top-3 z-[1000] w-[min(340px,calc(100%-4.5rem))]" />

                    <div className="absolute right-3 top-3 z-[1000] flex items-center gap-1 rounded-lg border border-line bg-surface/95 p-1 shadow-lg backdrop-blur">
                        <button
                            type="button"
                            onClick={resetView}
                            title="Reset to national extent"
                            aria-label="Reset to national extent"
                            className="grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                        >
                            <RotateCcw className="h-4 w-4" strokeWidth={1.75} />
                        </button>
                        <button
                            type="button"
                            onClick={toggleFullscreen}
                            title={isFullscreen ? 'Exit full screen' : 'Full screen'}
                            aria-label={isFullscreen ? 'Exit full screen' : 'Full screen'}
                            className="grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                        >
                            {isFullscreen ? <Minimize2 className="h-4 w-4" strokeWidth={1.75} /> : <Maximize2 className="h-4 w-4" strokeWidth={1.75} />}
                        </button>
                        <button
                            type="button"
                            onClick={() => setLayersOpen(true)}
                            title="Layers"
                            aria-label="Layers"
                            className="grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring lg:hidden"
                        >
                            <Layers className="h-4 w-4" strokeWidth={1.75} />
                        </button>
                    </div>

                    <MapLegend
                        basins={basin_legend}
                        stationTypes={station_types}
                        observationMode={rainfallFilter.active ? {
                            active: true,
                            parameterCode: rainfallFilter.parameter,
                            date: rainfallFilter.date,
                            from: rainfallFilter.from,
                            to: rainfallFilter.to,
                        } : null}
                        className="absolute bottom-14 left-3 z-[900] hidden lg:block"
                    />

                    {panelOpen && selected && (
                        <div className="absolute inset-x-3 bottom-3 z-[1000] flex max-h-[62%] flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xl sm:inset-x-auto sm:bottom-3 sm:right-3 sm:top-16 sm:w-[352px] sm:max-h-none">
                            <div className="flex items-center justify-between border-b border-line px-4 py-2.5">
                                <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    {selected.type === 'basin' ? 'Basin' : selected.type === 'sub_basin' ? 'Sub-basin' : 'Monitoring station'}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => setPanelOpen(false)}
                                    className="grid h-7 w-7 place-items-center rounded-md text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                                    aria-label="Close panel"
                                >
                                    <X className="h-4 w-4" strokeWidth={2} />
                                </button>
                            </div>

                            <div className="eec-scrollbar min-h-0 flex-1 overflow-y-auto p-4">
                                {selected.type === 'basin' && <BasinInfoPanel basin={selected.data} />}
                                {selected.type === 'sub_basin' && <SubBasinInfoPanel subBasin={selected.data} />}
                                {selected.type === 'station' && <StationInfoPanel detail={selected.data} />}
                            </div>
                        </div>
                    )}

                    {isEmpty && (
                        <div className="pointer-events-none absolute inset-0 z-[950] grid place-items-center p-4">
                            <div className="pointer-events-auto max-w-sm rounded-xl border border-line bg-surface/95 px-5 py-4 text-center shadow-xl backdrop-blur">
                                <MapPinIcon />
                                <p className="mt-2 text-[13px] font-semibold text-ink">No geographic datasets yet</p>
                                <p className="mt-1 text-[11.5px] leading-relaxed text-muted">
                                    Import a basin boundary from the registry, or add a national boundary, river or lake GeoJSON layer.
                                    Nothing is drawn until real coordinates exist.
                                </p>
                                {abilities.import && (
                                    <Button size="sm" className="mt-3" onClick={() => setImportOpen(true)}>
                                        <UploadCloud className="h-3.5 w-3.5" strokeWidth={1.75} />
                                        Import GeoJSON
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {layersOpen && (
                <div className="fixed inset-0 z-[1100] lg:hidden">
                    <div className="absolute inset-0 bg-ink/40" onClick={() => setLayersOpen(false)} aria-hidden="true" />
                    <div className="absolute inset-y-0 left-0 w-[310px] overflow-y-auto border-r border-line bg-surface p-3 shadow-2xl">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Map layers</p>
                            <button
                                type="button"
                                onClick={() => setLayersOpen(false)}
                                className="grid h-7 w-7 place-items-center rounded-md text-muted hover:bg-canvas hover:text-ink"
                                aria-label="Close layers"
                            >
                                <X className="h-4 w-4" strokeWidth={2} />
                            </button>
                        </div>
                        <LayerControl
                            layers={layers}
                            visible={visible}
                            onToggle={toggleLayer}
                            stationTypes={station_types}
                            activeTypes={activeTypes}
                            onToggleType={toggleStationType}
                            basemaps={basemapOptions}
                            basemap={basemap}
                            basemapStatus={basemap_status}
                            onBasemapChange={setBasemap}
                            hasBasinSelection={Boolean(selectedBasinId)}
                            canImport={abilities.import}
                            onImport={() => {
                                setLayersOpen(false);
                                setImportOpen(true);
                            }}
                            rainfallFilter={rainfallFilter}
                            onRainfallFilterChange={handleRainfallFilterChange}
                            onApplyRainfallFilter={() => {
                                setLayersOpen(false);
                                applyRainfallFilter();
                            }}
                            onClearRainfallFilter={() => {
                                setLayersOpen(false);
                                clearRainfallFilter();
                            }}
                        />
                    </div>
                </div>
            )}

            <ImportGeoJsonDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
                layers={importable_layers}
                basins={basin_legend}
            />

        </AppLayout>
    );
}

function MapPinIcon() {
    return (
        <span className="mx-auto grid h-9 w-9 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
            <Layers className="h-4 w-4" strokeWidth={1.75} />
        </span>
    );
}
