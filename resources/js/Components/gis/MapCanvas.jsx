import MarkerClusterLayer from '@/Components/gis/MarkerClusterLayer';
import { GeoJSON, MapContainer, ScaleControl, TileLayer, ZoomControl, useMap } from 'react-leaflet';
import { useEffect } from 'react';

const basinStyle = (feature, selected) => ({
    color: feature.properties.color,
    weight: selected ? 3.2 : 1.6,
    opacity: selected ? 1 : 0.9,
    fillColor: feature.properties.color,
    fillOpacity: selected ? 0.32 : 0.16,
});

const subBasinStyle = (feature, selected) => ({
    color: feature.properties.color,
    weight: selected ? 2.8 : 1.3,
    opacity: selected ? 1 : 0.85,
    dashArray: '5 4',
    fillColor: feature.properties.color,
    fillOpacity: selected ? 0.24 : 0.08,
});

function MapBridge({ onReady }) {
    const map = useMap();

    useEffect(() => {
        onReady?.(map);
    }, [map, onReady]);

    return null;
}

function FitBounds({ fit }) {
    const map = useMap();

    useEffect(() => {
        if (fit?.bounds) {
            map.fitBounds(fit.bounds, { padding: [28, 28], maxZoom: 12, animate: true });
        }
    }, [fit, map]);

    return null;
}

function FlyTo({ focus }) {
    const map = useMap();

    useEffect(() => {
        if (focus?.lat !== undefined && focus?.lng !== undefined) {
            map.flyTo([focus.lat, focus.lng], focus.zoom ?? 11, { duration: 0.8 });
        }
    }, [focus, map]);

    return null;
}

function ResizeSignal({ signal }) {
    const map = useMap();

    useEffect(() => {
        const timer = window.setTimeout(() => map.invalidateSize(), 220);

        return () => window.clearTimeout(timer);
    }, [signal, map]);

    return null;
}

export default function MapCanvas({
    center,
    zoom,
    minZoom = 4,
    maxZoom = 18,
    basemap,
    basins,
    subBasins,
    stations,
    visibleLayers,
    selectedBasinId,
    selectedSubBasinId,
    nationalBoundary,
    rivers,
    lakes,
    watersheds,
    onBasinSelect,
    onSubBasinSelect,
    onStationSelect,
    onReady,
    fit,
    focus,
    resizeSignal,
}) {
    return (
        <MapContainer
            center={center}
            zoom={zoom}
            minZoom={minZoom}
            maxZoom={maxZoom}
            zoomControl={false}
            scrollWheelZoom
            className="h-full w-full"
        >
            <MapBridge onReady={onReady} />
            <FitBounds fit={fit} />
            <FlyTo focus={focus} />
            <ResizeSignal signal={resizeSignal} />

            <TileLayer
                key={basemap.id}
                url={basemap.url}
                attribution={basemap.attribution}
                subdomains={basemap.subdomains ?? 'abc'}
                maxZoom={basemap.max_zoom ?? 19}
            />

            <ZoomControl position="bottomright" />
            <ScaleControl position="bottomleft" imperial={false} />

            {visibleLayers.national_boundary && nationalBoundary && (
                <GeoJSON
                    data={nationalBoundary}
                    style={{ color: '#334155', weight: 1.8, opacity: 0.85, fill: false, dashArray: '7 5' }}
                    interactive={false}
                />
            )}

            {visibleLayers.lakes && lakes && (
                <GeoJSON
                    data={lakes}
                    style={{ color: '#0369A1', weight: 1, fillColor: '#0EA5E9', fillOpacity: 0.35 }}
                    onEachFeature={(feature, layer) => {
                        layer.bindTooltip(feature.properties.name, { sticky: true, className: 'eec-map-tooltip' });
                    }}
                />
            )}

            {visibleLayers.rivers && rivers && (
                <GeoJSON
                    data={rivers}
                    style={{ color: '#0284C7', weight: 1.6, opacity: 0.8 }}
                    onEachFeature={(feature, layer) => {
                        layer.bindTooltip(feature.properties.name, { sticky: true, className: 'eec-map-tooltip' });
                    }}
                />
            )}

            {visibleLayers.watersheds && watersheds && (
                <GeoJSON
                    data={watersheds}
                    style={{ color: '#7C3AED', weight: 1, opacity: 0.7, dashArray: '2 3', fillColor: '#7C3AED', fillOpacity: 0.05 }}
                    onEachFeature={(feature, layer) => {
                        layer.bindTooltip(feature.properties.name, { sticky: true, className: 'eec-map-tooltip' });
                    }}
                />
            )}

            {visibleLayers.basins && basins && (
                <GeoJSON
                    key={`basins-${selectedBasinId ?? 'none'}-${basins.features?.length ?? 0}`}
                    data={basins}
                    style={(feature) => basinStyle(feature, feature.properties.id === selectedBasinId)}
                    onEachFeature={(feature, layer) => {
                        const selected = feature.properties.id === selectedBasinId;
                        const props = feature.properties;
                        const areaText = props.area_km2
                            ? `${Number(props.area_km2).toLocaleString()} km²`
                            : 'Area pending';
                        const coordsText = (props.centroid_lat && props.centroid_lng)
                            ? `${Number(props.centroid_lat).toFixed(4)}°N, ${Number(props.centroid_lng).toFixed(4)}°E`
                            : null;

                        layer.bindTooltip(
                            `<strong>${props.name}</strong> (${props.code})<br/><span style="color:#0284c7; font-weight:600;">${areaText}</span>`,
                            {
                                sticky: true,
                                direction: 'top',
                                className: 'eec-map-tooltip',
                                opacity: 1,
                            },
                        );

                        const popupHtml = `
                            <div style="font-family: inherit; font-size: 12px; line-height: 1.45; min-width: 220px; padding: 2px;">
                                <div style="display:flex; align-items:center; gap:6px; margin-bottom: 6px;">
                                    <span style="width: 11px; height: 11px; border-radius: 2px; background: ${props.color}; shrink: 0;"></span>
                                    <strong style="font-size: 13.5px; color: #0f172a;">${props.name}</strong>
                                    <span style="font-size: 11px; color: #64748b;">(${props.code})</span>
                                </div>
                                <div style="border-top: 1px solid #e2e8f0; padding-top: 6px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; font-size: 11px;">
                                    <div><span style="color:#64748b;">Area:</span> <strong>${areaText}</strong></div>
                                    <div><span style="color:#64748b;">Stations:</span> <strong>${props.stations_count ?? 0}</strong></div>
                                    <div><span style="color:#64748b;">Sub-basins:</span> <strong>${props.sub_basins_count ?? 0}</strong></div>
                                    ${coordsText ? `<div><span style="color:#64748b;">Centroid:</span> <strong>${coordsText}</strong></div>` : ''}
                                </div>
                                <div style="margin-top: 8px; border-top: 1px solid #f1f5f9; padding-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 600;">${props.data_status ?? 'verified'}</span>
                                    <a href="/basins/${props.id}" style="color: #0284c7; font-weight: 600; text-decoration: none; font-size: 11px;">Open Basin →</a>
                                </div>
                            </div>
                        `;
                        layer.bindPopup(popupHtml, { className: 'eec-leaflet-popup' });

                        layer.on({
                            mouseover: () => {
                                layer.setStyle({ weight: 3, fillOpacity: Math.min(0.55, 0.36) });
                                layer.bringToFront();
                            },
                            mouseout: () => {
                                layer.setStyle(basinStyle(feature, selected));
                            },
                            click: () => onBasinSelect?.(feature.properties),
                        });
                    }}
                />
            )}

            {visibleLayers.sub_basins && subBasins && (
                <GeoJSON
                    key={`sub-basins-${selectedSubBasinId ?? 'none'}-${subBasins.features?.length ?? 0}`}
                    data={subBasins}
                    style={(feature) => subBasinStyle(feature, feature.properties.id === selectedSubBasinId)}
                    onEachFeature={(feature, layer) => {
                        const selected = feature.properties.id === selectedSubBasinId;
                        const props = feature.properties;
                        const areaText = props.area_km2
                            ? `${Number(props.area_km2).toLocaleString()} km²`
                            : 'Area pending';

                        layer.bindTooltip(
                            `<strong>${props.name}</strong> · ${props.code}<br/><span style="color:#0284c7; font-weight:600;">${areaText}</span>`,
                            {
                                sticky: true,
                                className: 'eec-map-tooltip',
                            },
                        );

                        const popupHtml = `
                            <div style="font-family: inherit; font-size: 12px; line-height: 1.45; min-width: 200px; padding: 2px;">
                                <div style="display:flex; align-items:center; gap:6px; margin-bottom: 6px;">
                                    <span style="width: 10px; height: 10px; border-radius: 2px; background: ${props.color}; shrink: 0;"></span>
                                    <strong style="font-size: 13px; color: #0f172a;">${props.name}</strong>
                                </div>
                                <div style="border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 11px; display: flex; flex-direction: column; gap: 3px;">
                                    <div><span style="color:#64748b;">Code:</span> <strong>${props.code}</strong></div>
                                    <div><span style="color:#64748b;">Area:</span> <strong>${areaText}</strong></div>
                                    <div><span style="color:#64748b;">Stations:</span> <strong>${props.stations_count ?? 0}</strong></div>
                                </div>
                                <div style="margin-top: 8px; text-align: right;">
                                    <a href="${props.href ?? `/sub-basins/${props.id}`}" style="color: #0284c7; font-weight: 600; text-decoration: none; font-size: 11px;">Sub-basin Details →</a>
                                </div>
                            </div>
                        `;
                        layer.bindPopup(popupHtml, { className: 'eec-leaflet-popup' });

                        layer.on({
                            mouseover: () => layer.setStyle({ weight: 2.8, fillOpacity: 0.28 }),
                            mouseout: () => layer.setStyle(subBasinStyle(feature, selected)),
                            click: () => onSubBasinSelect?.(feature.properties),
                        });
                    }}
                />
            )}

            {visibleLayers.stations && (
                <MarkerClusterLayer features={stations?.features ?? []} onSelect={onStationSelect} />
            )}
        </MapContainer>
    );
}
