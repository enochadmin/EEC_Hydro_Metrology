/**
 * Basemap styles are served by the backend (`config/gis.php`) so a keyed style
 * is only offered when its key is present and accepted. This constant is the
 * last-resort fallback: the keyless terrain tiles, used when a page renders
 * before the props arrive.
 */
export const FALLBACK_BASEMAP = {
    id: 'terrain',
    label: 'Terrain',
    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
    attribution: 'Tiles &copy; Esri, HERE, Garmin, USGS, &copy; OpenStreetMap contributors',
    max_zoom: 19,
};
