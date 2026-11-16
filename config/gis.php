<?php

/**
 * GIS vocabulary shared by the API layer and the React map.
 *
 * Layer labels, station-type styling, the basin palette and the basemap
 * registry live here so the map legend, filters and server payloads cannot
 * drift apart. Raster layers are declared but marked unavailable until a
 * verified dataset is loaded; they are never rendered as empty tiles.
 */
return [
    'layers' => [
        'basins' => [
            'label' => 'Major basins',
            'group' => 'Boundaries',
            'geometry' => 'polygon',
            'description' => 'Primary basin polygons imported from verified GeoJSON.',
        ],
        'sub_basins' => [
            'label' => 'Sub-basins',
            'group' => 'Boundaries',
            'geometry' => 'polygon',
            'description' => 'Catchment polygons shown when a basin is selected.',
        ],
        'watersheds' => [
            'label' => 'Watersheds',
            'group' => 'Boundaries',
            'geometry' => 'polygon',
            'description' => 'Third-level catchment boundaries from the watershed registry.',
        ],
        'national_boundary' => [
            'label' => 'National boundary',
            'group' => 'Reference',
            'geometry' => 'polygon',
            'description' => 'Country outline imported by a GIS officer, shown as an outline only.',
        ],
        'rivers' => [
            'label' => 'Rivers',
            'group' => 'Reference',
            'geometry' => 'line',
            'description' => 'River centre lines imported as GeoJSON.',
        ],
        'lakes' => [
            'label' => 'Lakes',
            'group' => 'Reference',
            'geometry' => 'polygon',
            'description' => 'Lake and reservoir polygons imported as GeoJSON.',
        ],
        'stations' => [
            'label' => 'Monitoring stations',
            'group' => 'Monitoring',
            'geometry' => 'point',
            'description' => 'Stations with confirmed coordinates, clustered by zoom level.',
        ],
        'rainfall' => [
            'label' => 'Rainfall distribution',
            'group' => 'Raster',
            'geometry' => 'raster',
            'description' => 'Interpolated rainfall surface.',
            'available' => false,
            'reason' => 'No verified rainfall raster has been loaded. The layer stays hidden until a dataset is registered.',
        ],
        'temperature' => [
            'label' => 'Temperature distribution',
            'group' => 'Raster',
            'geometry' => 'raster',
            'description' => 'Interpolated temperature surface.',
            'available' => false,
            'reason' => 'No verified temperature raster has been loaded. The layer stays hidden until a dataset is registered.',
        ],
        'elevation' => [
            'label' => 'Elevation / DEM',
            'group' => 'Raster',
            'geometry' => 'raster',
            'description' => 'Digital elevation model.',
            'available' => false,
            'reason' => 'No DEM has been registered. The layer stays hidden until a verified raster is available.',
        ],
    ],

    'station_types' => [
        'river_gauge' => ['label' => 'Streamflow / river gauge', 'group' => 'Hydrological', 'color' => '#1F6076', 'icon' => 'waves'],
        'water_quality' => ['label' => 'Water quality', 'group' => 'Hydrological', 'color' => '#0E7490', 'icon' => 'flask'],
        'reservoir' => ['label' => 'Reservoir', 'group' => 'Hydrological', 'color' => '#4F46E5', 'icon' => 'dam'],
        'spring' => ['label' => 'Spring', 'group' => 'Hydrological', 'color' => '#0F766E', 'icon' => 'droplet'],
        'rainfall' => ['label' => 'Rainfall', 'group' => 'Rainfall', 'color' => '#0891B2', 'icon' => 'rain'],
        'meteorological' => ['label' => 'Meteorological', 'group' => 'Meteorological', 'color' => '#D97706', 'icon' => 'thermometer'],
        'other' => ['label' => 'Other', 'group' => 'Other', 'color' => '#64748B', 'icon' => 'circle'],
    ],

    /**
     * Distinguishable fills for the twelve major basins, assigned by position
     * after ordering on sort_order and name.
     */
    'basin_palette' => [
        '#0072B2',
        '#D55E00',
        '#009E73',
        '#CC79A7',
        '#E69F00',
        '#56B4E9',
        '#8C564B',
        '#7F3C8D',
        '#2CA02C',
        '#B8860B',
        '#1F6076',
        '#C2185B',
    ],

    /*
    |--------------------------------------------------------------------------
    | Basemaps
    |--------------------------------------------------------------------------
    |
    | Every style below was requested and confirmed to return map imagery rather
    | than a provider notice. Keyless styles are always offered. Styles that
    | declare `requires_key` are offered only when that key is configured and
    | the `php artisan gis:basemap-key` probe has not rejected it, so a bad key
    | can never paint a provider watermark across the basin polygons.
    |
    */

    'basemaps' => [
        [
            'id' => 'terrain',
            'label' => 'Terrain',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Tiles &copy; Esri, HERE, Garmin, USGS, Intermap, INCREMENT P, NRCan, METI, Esri China (Hong Kong), Esri Korea, Esri (Thailand), NGCC, &copy; OpenStreetMap contributors',
            'max_zoom' => 19,
        ],
        [
            'id' => 'imagery',
            'label' => 'Satellite',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Tiles &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community',
            'max_zoom' => 19,
        ],
        [
            'id' => 'streets',
            'label' => 'Streets',
            'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '&copy; OpenStreetMap contributors',
            'max_zoom' => 19,
        ],
        [
            'id' => 'light',
            'label' => 'Light',
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
            'subdomains' => 'abcd',
            'max_zoom' => 19,
            'requires_key' => 'carto',
        ],
        [
            'id' => 'dark',
            'label' => 'Dark',
            'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
            'subdomains' => 'abcd',
            'max_zoom' => 19,
            'requires_key' => 'carto',
        ],
        [
            'id' => 'voyager',
            'label' => 'Voyager',
            'url' => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
            'subdomains' => 'abcd',
            'max_zoom' => 19,
            'requires_key' => 'carto',
        ],
    ],

    'basemap_keys' => [
        'carto' => env('CARTO_BASEMAPS_KEY'),
    ],

    /**
     * One unkeyed tile the probe compares against an authenticated request:
     * byte-identical responses mean the key was not recognised.
     */
    'basemap_key_probe' => 'https://basemaps.cartocdn.com/rastertiles/voyager/3/4/2.png',

    'default_basemap' => env('GIS_DEFAULT_BASEMAP', 'terrain'),

    'simplification' => [
        'overview' => 0.008,
        'detail' => 0.0015,
        'min' => 0.0,
        'max' => 0.05,
    ],

    'limits' => [
        'max_features_per_import' => 5000,
        'max_positions_per_import' => 20000,
        'max_upload_kb' => 20480,
        'max_station_points' => 5000,
    ],
];
