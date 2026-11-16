<?php

/**
 * Excel ingestion vocabulary.
 *
 * Structure detection works from cell content, not worksheet names. The
 * aliases below only translate measurement codes found in files (PRECIP,
 * TEMP…) to the parameter codes stored in the database; parameters themselves
 * are never created from an upload.
 */
return [
    'limits' => [
        'max_upload_kb' => 30720,
        'preview_rows' => 40,
        'preview_columns' => 40,
        'import_chunk' => 500,
        'max_issues_stored' => 5000,
    ],

    'structures' => [
        'monthly_daily_columns' => 'Monthly records with daily value columns',
        'multi_header_matrix' => 'Multi-row header matrix (year × month × day)',
        'daily_series' => 'Date-based daily observations',
    ],

    /**
     * Normalised element label => EEC parameter code.
     */
    'element_aliases' => [
        'precip' => 'rainfall',
        'precipitation' => 'rainfall',
        'rain' => 'rainfall',
        'rainfall' => 'rainfall',
        'prcp' => 'rainfall',
        'temp' => 'temperature',
        'temperature' => 'temperature',
        'airtemp' => 'temperature',
        'rh' => 'relative_humidity',
        'humidity' => 'relative_humidity',
        'wind' => 'wind_speed',
        'windspeed' => 'wind_speed',
        'sun' => 'sunshine_duration',
        'sunshine' => 'sunshine_duration',
        'flow' => 'streamflow',
        'streamflow' => 'streamflow',
        'discharge' => 'streamflow',
        'waterlevel' => 'water_level',
        'level' => 'water_level',
    ],

    /**
     * Columns that identify the monthly wide format. Keys are matched after
     * lower-casing and removing spaces/punctuation.
     */
    'monthly_columns' => [
        'station' => ['name', 'station', 'stationname', 'sitename'],
        'elevation' => ['elevation', 'elev', 'altitude'],
        'longitude' => ['geogr1', 'longitude', 'lon', 'xcoord', 'long'],
        'latitude' => ['geogr2', 'latitude', 'lat', 'ycoord'],
        'parameter' => ['element', 'parameter', 'variable', 'measurement'],
        'year' => ['year', 'yr'],
        'month' => ['month', 'mon', 'mm'],
        'time' => ['time', 'hour', 'observationtime'],
    ],

    'station_defaults' => [
        'type' => 'rainfall',
        'data_status' => 'provisional',
    ],
];
