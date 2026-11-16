<?php

/**
 * Reporting vocabulary.
 *
 * The single most important rule in this module: a parameter is aggregated
 * according to what it measures, never with one generic reducer. Rainfall is an
 * accumulation and is summed; temperature and streamflow are states and are
 * averaged, with minima and maxima reported separately.
 *
 * `value_type` on the parameter row is the authority for that decision. The
 * list below is the machine-readable form of it.
 */
return [
    // Value types that describe an accumulation over the recording period.
    'accumulating_value_types' => ['daily_total', 'monthly_total', 'annual_total'],

    'granularities' => ['daily', 'monthly', 'annual'],

    // Chart payload guard: analysis returns at most this many buckets.
    'max_points' => 1500,

    // Guard for exports and preview tables.
    'max_rows' => 50000,

    'distribution_bins' => 12,

    /*
    | Quality statuses whose observations are still provisional. Reading them
    | requires `export restricted reports`; this is what makes an export
    | "restricted" rather than a merely large one.
    */
    'restricted_statuses' => ['pending', 'flagged', 'rejected'],

    'quality_filter_labels' => [
        'approved' => 'Approved records only',
        'all' => 'All records, including provisional',
    ],

    // Generated documents are written here and deleted after the download.
    'export_path' => 'app/private/reports',
];
