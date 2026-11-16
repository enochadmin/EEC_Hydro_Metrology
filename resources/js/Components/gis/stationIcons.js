import L from 'leaflet';

/**
 * Inline SVG markers.
 *
 * Leaflet's default icon depends on image assets that Vite rewrites, so the map
 * uses divIcons instead: one coloured badge per station type, no asset paths.
 */
const PATHS = {
    waves: `
        <path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5s2.5 2 5 2 2.5-2 5-2" />
        <path d="M2 12c.6.5 1.2 1 2.5 1C7 13 7 11 9.5 11s2.5 2 5 2 2.5-2 5-2" />
        <path d="M2 18c.6.5 1.2 1 2.5 1C7 19 7 17 9.5 17s2.5 2 5 2 2.5-2 5-2" />
    `,
    rain: `
        <path d="M4 14.9A7 7 0 1 1 15.7 8h1.8a3.5 3.5 0 1 1 0 7H6" />
        <path d="M8 19v2" /><path d="M12 18v3" /><path d="M16 19v2" />
    `,
    thermometer: `
        <path d="M14 4v10.5a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z" />
        <path d="M12 9v6" />
    `,
    flask: `
        <path d="M10 2v7L4.5 19a1 1 0 0 0 .9 1.5h13.2a1 1 0 0 0 .9-1.5L14 9V2" />
        <path d="M8.5 2h7" /><path d="M7 16h10" />
    `,
    dam: `
        <path d="M3 9h18" /><path d="M3 15h18" />
        <path d="M7 9v6" /><path d="M12 9v6" /><path d="M17 9v6" />
    `,
    droplet: `
        <path d="M12 2.7 6.2 8.5a8 8 0 1 0 11.6 0Z" />
    `,
    circle: `<circle cx="12" cy="12" r="4" />`,
};

export function stationIcon({ color = '#1F6076', icon = 'waves' } = {}) {
    const paths = PATHS[icon] ?? PATHS.circle;

    return L.divIcon({
        className: 'eec-station-marker-wrapper',
        html: `<span class="eec-station-marker" style="--station-color:${color}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>
        </span>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });
}

export function clusterIcon(count) {
    const size = count >= 100 ? 44 : count >= 10 ? 40 : 34;

    return L.divIcon({
        className: 'eec-cluster-wrapper',
        html: `<span class="eec-cluster" style="width:${size}px;height:${size}px">${count}</span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
}
