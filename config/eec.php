<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application identity
    |--------------------------------------------------------------------------
    |
    | Branding values are shared with the frontend through HandleInertiaRequests
    | so the shell, page titles and about panels stay in sync.
    |
    */

    'app' => [
        'short_name' => 'EEC-BHDMS',
        'full_name' => 'EEC Basin & Hydrological Data Management System',
        'version' => '0.8.0',
        'phase' => 'Phase 8 — Excel transformation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Geographic defaults
    |--------------------------------------------------------------------------
    |
    | Used to initialise Leaflet in later phases. Coordinates describe the
    | centre of Ethiopia and are not station or basin measurements.
    |
    */

    'map' => [
        'country' => 'Ethiopia',
        'center' => [
            'lat' => 9.145,
            'lng' => 40.489673,
        ],
        'zoom' => 6,
        'min_zoom' => 5,
        'max_zoom' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Basin registry expectations
    |--------------------------------------------------------------------------
    |
    | The registry itself is delivered in Phase 5. These values describe the
    | required scope, they are not hydrological observations.
    |
    */

    'basins' => [
        'expected_count' => 12,
        'hierarchy' => ['basin', 'sub_basin', 'watershed'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module roadmap
    |--------------------------------------------------------------------------
    |
    | Surfaced on the dashboard so the shell communicates what exists and what
    | is still to be built. Status values: planned, in_progress, available.
    |
    */

    'modules' => [
        [
            'key' => 'basins',
            'name' => 'Basin management',
            'description' => 'Registry of the major river basins with codes, boundaries and metadata.',
            'phase' => 5,
            'status' => 'available',
        ],
        [
            'key' => 'sub_basins',
            'name' => 'Sub-basin & watershed hierarchy',
            'description' => 'Configurable sub-basin and catchment tree beneath every basin.',
            'phase' => 5,
            'status' => 'available',
        ],
        [
            'key' => 'gis',
            'name' => 'Interactive GIS map',
            'description' => 'Leaflet map with basin boundaries, station layers, filters and GeoJSON exchange.',
            'phase' => 6,
            'status' => 'available',
        ],
        [
            'key' => 'stations',
            'name' => 'Monitoring stations',
            'description' => 'Station registry with coordinates, elevation, gauge type and operational status.',
            'phase' => 7,
            'status' => 'planned',
        ],
        [
            'key' => 'imports',
            'name' => 'Excel upload & transformation',
            'description' => 'Staged workbook ingestion, column mapping and validated imports.',
            'phase' => 8,
            'status' => 'available',
        ],
        [
            'key' => 'records',
            'name' => 'Hydrological database',
            'description' => 'Time-series storage for water level, discharge, rainfall and water quality.',
            'phase' => 9,
            'status' => 'planned',
        ],
        [
            'key' => 'quality',
            'name' => 'Data quality control',
            'description' => 'Validation rules, gap detection, outlier flags and approval workflow.',
            'phase' => 9,
            'status' => 'planned',
        ],
        [
            'key' => 'reports',
            'name' => 'Reports & analytics',
            'description' => 'Basin and station reporting, hydrographs and exportable summaries.',
            'phase' => 10,
            'status' => 'available',
        ],
    ],

];
