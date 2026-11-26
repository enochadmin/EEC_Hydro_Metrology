<?php

/**
 * Station network vocabulary and registry-import defaults.
 *
 * `classes` are the observing network classes used by the Ethiopian met
 * network lists. `station_type` is what the station measures and stays in
 * `config('gis.station_types')`; a class only refines it. Class labels and
 * colours live here so the registry, the importer and the map popup cannot
 * drift apart.
 */
return [
    'classes' => [
        'synoptic' => [
            'label' => 'Synoptic',
            'station_type' => 'meteorological',
            'color' => '#7C3AED',
            'description' => 'Full synoptic observation programme reported at main hours.',
        ],
        'principal' => [
            'label' => 'Principal',
            'station_type' => 'meteorological',
            'color' => '#D97706',
            'description' => 'Principal meteorological station with a broad observation set.',
        ],
        'ordinary' => [
            'label' => 'Ordinary',
            'station_type' => 'meteorological',
            'color' => '#0F766E',
            'description' => 'Ordinary meteorological station with a reduced observation set.',
        ],
        'precipitation' => [
            'label' => 'Precipitation',
            'station_type' => 'rainfall',
            'color' => '#0891B2',
            'description' => 'Rain gauge reporting precipitation only.',
        ],
        'other' => [
            'label' => 'Unclassified',
            'station_type' => 'meteorological',
            'color' => '#64748B',
            'description' => 'Class not stated in the source list.',
        ],
    ],

    'import' => [
        // Ethiopia's national grid is UTM zone 37N (central meridian 39°E).
        'utm_zone' => 37,

        // Default code prefix for rows that carry no station code.
        'code_prefix' => 'MET',

        // Parameters attached to every newly created station. Operators can
        // change this per import; the list is intersected with active
        // catalogue parameters before use.
        'default_parameters' => ['rainfall', 'temperature'],

        'default_data_status' => 'provisional',

        // A candidate closer than this to a registered station with the same
        // name is treated as the same station rather than a second record.
        'duplicate_distance_m' => 1000,

        'max_rows' => 20000,
        'max_upload_kb' => 20480,
    ],
];
