import { FALLBACK_BASEMAP } from '@/Components/gis/basemaps';
import { cn } from '@/Utils/cn';
import { router } from '@inertiajs/react';
import { GeoJSON, MapContainer, TileLayer } from 'react-leaflet';
import { useEffect, useState } from 'react';

const ETHIOPIA_CENTER = [9.145, 40.489673];

/**
 * Compact dashboard map.
 *
 * It loads only the simplified basin polygons — no stations and no readings —
 * so the dashboard stays light. A click opens the full GIS page with that basin
 * preselected.
 */
export default function BasinMiniMap({ className, height = 250, basemap = FALLBACK_BASEMAP }) {
    const [data, setData] = useState(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        fetch('/gis/data/basins?tolerance=0.02', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('Map data unavailable'))))
            .then(setData)
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    setFailed(true);
                }
            });

        return () => controller.abort();
    }, []);

    const count = data?.features?.length ?? 0;

    return (
        <div className={cn('relative overflow-hidden rounded-lg border border-line bg-canvas', className)} style={{ height }}>
            <MapContainer
                center={ETHIOPIA_CENTER}
                zoom={5}
                minZoom={4}
                maxZoom={10}
                scrollWheelZoom={false}
                zoomControl={false}
                attributionControl={false}
                className="h-full w-full"
            >
                <TileLayer
                    key={basemap.id}
                    url={basemap.url}
                    attribution={basemap.attribution}
                    subdomains={basemap.subdomains ?? 'abc'}
                    maxZoom={basemap.max_zoom ?? 19}
                />

                {data && (
                    <GeoJSON
                        data={data}
                        style={(feature) => ({
                            color: feature.properties.color,
                            weight: 1.2,
                            fillColor: feature.properties.color,
                            fillOpacity: 0.2,
                        })}
                        onEachFeature={(feature, layer) => {
                            layer.bindTooltip(feature.properties.name, { sticky: true, className: 'eec-map-tooltip' });
                            layer.on('click', () => router.visit(`/gis?basin=${feature.properties.id}`));
                        }}
                    />
                )}
            </MapContainer>

            {failed && (
                <div className="absolute inset-0 grid place-items-center bg-canvas/80 px-4 text-center">
                    <p className="text-[11.5px] text-muted">Map data could not be loaded.</p>
                </div>
            )}

            {!failed && data && count === 0 && (
                <div className="pointer-events-none absolute inset-0 grid place-items-center px-6 text-center">
                    <div className="rounded-lg border border-line bg-surface/95 px-4 py-3 shadow-sm">
                        <p className="text-[12px] font-medium text-ink">No basin polygons yet</p>
                        <p className="mt-0.5 text-[10.5px] leading-relaxed text-muted">
                            Import a basin boundary to draw it here and in the full map.
                        </p>
                    </div>
                </div>
            )}

            <div className="pointer-events-none absolute bottom-2 right-2 rounded bg-surface/90 px-1.5 py-0.5 text-[9.5px] text-muted">
                {count} mapped {count === 1 ? 'basin' : 'basins'}
            </div>
        </div>
    );
}
