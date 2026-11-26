import { clusterIcon, stationIcon } from '@/Components/gis/stationIcons';
import L from 'leaflet';
import 'leaflet.markercluster';
import { useEffect, useRef } from 'react';
import { useMap } from 'react-leaflet';

/**
 * Color and radius scale for rainfall / observation graduated markers.
 */
function getObservationStyle(value, maxValue = 100) {
    const val = Number(value) || 0;

    if (val <= 20) {
        return { radius: 8, stroke: '#0284c7', fill: '#bae6fd' };
    }
    if (val <= 50) {
        return { radius: 11, stroke: '#0284c7', fill: '#38bdf8' };
    }
    if (val <= 150) {
        return { radius: 15, stroke: '#0369a1', fill: '#0284c7' };
    }
    if (val <= 300) {
        return { radius: 19, stroke: '#1e3a8a', fill: '#1d4ed8' };
    }
    return { radius: 24, stroke: '#172554', fill: '#1e3a8a' };
}

/**
 * Imperative marker-cluster layer.
 *
 * Supports both standard station pins and graduated circle markers when observation
 * filters (e.g. rainfall totals) are active.
 */
export default function MarkerClusterLayer({ features = [], onSelect }) {
    const map = useMap();
    const selectRef = useRef(onSelect);
    selectRef.current = onSelect;

    useEffect(() => {
        const isObservationMode = features.length > 0 && features[0]?.properties?.value !== undefined;

        const group = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: isObservationMode ? 38 : 46,
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
            iconCreateFunction: (cluster) => clusterIcon(cluster.getChildCount()),
        });

        features.forEach((feature) => {
            const [lng, lat] = feature.geometry?.coordinates ?? [];

            if (typeof lat !== 'number' || typeof lng !== 'number') {
                return;
            }

            const properties = feature.properties ?? {};
            const isObservation = properties.value !== undefined;
            const coordsStr = `${lat.toFixed(4)}°N, ${lng.toFixed(4)}°E`;
            const elevStr = properties.elevation_m ? `${properties.elevation_m} m` : 'N/A';
            const basinName = properties.basin ? `${properties.basin.code} — ${properties.basin.name}` : 'N/A';

            let marker;

            if (isObservation) {
                const style = getObservationStyle(properties.value, properties.max_value);
                marker = L.circleMarker([lat, lng], {
                    radius: style.radius,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: style.fill,
                    fillOpacity: 0.88,
                    className: 'eec-observation-marker',
                });

                const unit = properties.parameter_code === 'temperature' ? '°C' : (properties.parameter_code === 'streamflow' ? 'm³/s' : 'mm');

                marker.bindTooltip(
                    `<strong>${properties.name}</strong> (${properties.code})<br/><span style="color:${style.fill}; font-weight:700; font-size:12px;">${Number(properties.value).toLocaleString()} ${unit}</span> · ${properties.completeness_pct}% data`,
                    { sticky: true, direction: 'top', className: 'eec-map-tooltip' },
                );

                const popupHtml = `
                    <div style="font-family: inherit; font-size: 12px; line-height: 1.45; min-width: 230px; padding: 2px;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom: 6px;">
                            <span style="width: 12px; height: 12px; border-radius: 50%; background: ${style.fill}; border: 1.5px solid #ffffff; shrink: 0;"></span>
                            <strong style="font-size: 13.5px; color: #1e293b;">${properties.name}</strong>
                            <span style="font-size: 11px; color: #64748b;">(${properties.code})</span>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-bottom: 8px;">
                            <div style="font-size: 10px; text-transform: uppercase; font-weight: 600; color: #64748b; letter-spacing: 0.05em;">Total ${properties.parameter_code ?? 'Rainfall'}</div>
                            <div style="font-size: 18px; font-weight: 700; color: #0284c7; margin: 2px 0;">${Number(properties.value).toLocaleString()} <span style="font-size: 12px; font-weight: 500;">${unit}</span></div>
                            <div style="font-size: 10.5px; color: #64748b; display: flex; justify-content: space-between;">
                                <span>Daily Avg: <strong>${properties.avg_value ?? '—'} ${unit}</strong></span>
                                <span>Completeness: <strong>${properties.completeness_pct}%</strong></span>
                            </div>
                            <div style="margin-top: 5px; height: 5px; width: 100%; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: ${Math.min(100, properties.completeness_pct)}%; background: #0284c7; border-radius: 9999px;"></div>
                            </div>
                        </div>
                        <div style="border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 11px; display: flex; flex-direction: column; gap: 3px;">
                            <div><span style="color:#64748b;">Days with data:</span> <strong>${properties.days_with_data} days</strong></div>
                            <div><span style="color:#64748b;">Missing days:</span> <strong>${properties.missing_days} days</strong></div>
                            <div><span style="color:#64748b;">Coordinates:</span> <strong>${coordsStr}</strong></div>
                        </div>
                        <div style="margin-top: 8px; text-align: right; border-top: 1px solid #f1f5f9; padding-top: 5px;">
                            <a href="/stations/${properties.id}" style="color: #0284c7; font-weight: 600; text-decoration: none; font-size: 11px;">Open station →</a>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupHtml, { className: 'eec-leaflet-popup' });
            } else {
                marker = L.marker([lat, lng], {
                    icon: stationIcon({ color: properties.color, icon: properties.icon }),
                    title: `${properties.name} (${properties.code})${properties.class_label ? ' · ' + properties.class_label : ''}`,
                    keyboard: true,
                });

                marker.bindTooltip(
                    `<strong>${properties.name}</strong> (${properties.code})<br/><span style="color:#64748b;">${properties.type_label ?? properties.station_type}</span>`,
                    { sticky: true, direction: 'top', className: 'eec-map-tooltip' },
                );

                const popupHtml = `
                    <div style="font-family: inherit; font-size: 12px; line-height: 1.45; min-width: 210px; padding: 2px;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom: 6px;">
                            <span style="width: 10px; height: 10px; border-radius: 50%; background: ${properties.color}; shrink: 0;"></span>
                            <strong style="font-size: 13px; color: #1e293b;">${properties.name}</strong>
                        </div>
                        <div style="border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 11px; display: flex; flex-direction: column; gap: 3px;">
                            <div><span style="color:#64748b;">Code:</span> <strong>${properties.code}</strong></div>
                            <div><span style="color:#64748b;">Type:</span> <strong>${properties.type_label ?? properties.station_type}</strong></div>
                            ${properties.class_label ? `<div><span style="color:#64748b;">Class:</span> <strong>${properties.class_label}</strong></div>` : ''}
                            <div><span style="color:#64748b;">Basin:</span> <strong>${basinName}</strong></div>
                            <div><span style="color:#64748b;">Coordinates:</span> <strong>${coordsStr}</strong></div>
                            <div><span style="color:#64748b;">Elevation:</span> <strong>${elevStr}</strong></div>
                            ${properties.operator ? `<div><span style="color:#64748b;">Operator:</span> <strong>${properties.operator}</strong></div>` : ''}
                        </div>
                        <div style="margin-top: 8px; text-align: right; border-top: 1px solid #f1f5f9; padding-top: 5px;">
                            <a href="/stations/${properties.id}" style="color: #0284c7; font-weight: 600; text-decoration: none; font-size: 11px;">Open station →</a>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupHtml, { className: 'eec-leaflet-popup' });
            }

            marker.on('click', () => selectRef.current?.(properties));
            group.addLayer(marker);
        });

        map.addLayer(group);

        return () => {
            map.removeLayer(group);
        };
    }, [features, map]);

    return null;
}
