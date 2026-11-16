<?php

/**
 * Administrator-editable settings.
 *
 * Every entry maps to a real config path, and SystemSettingService pushes the
 * stored value into that path at boot. A setting that nothing reads would be a
 * promise the application does not keep, so the registry is deliberately small:
 * branding, map defaults, import limits and quality targets — the four areas the
 * running code actually consults.
 */
return [
    'cache_key' => 'eec.system-settings',

    'cache_ttl' => 3600,

    'groups' => [
        'branding' => [
            'label' => 'Branding',
            'description' => 'Names shown in the interface, the browser tab and outgoing mail.',
            'settings' => [
                'app_name' => [
                    'label' => 'Application name',
                    'type' => 'string',
                    'config' => 'app.name',
                    'default' => 'EEC-BHDMS',
                    'description' => 'Shown in the browser title and the sidebar wordmark.',
                    'rules' => ['required', 'string', 'max:60'],
                ],
                'organisation' => [
                    'label' => 'Organisation',
                    'type' => 'string',
                    'config' => 'eec.organisation',
                    'default' => 'Ethiopian Electric Corporation',
                    'description' => 'Owning organisation, shown alongside the application name.',
                    'rules' => ['required', 'string', 'max:120'],
                ],
                'support_email' => [
                    'label' => 'Sender address',
                    'type' => 'string',
                    'config' => 'mail.from.address',
                    'default' => 'no-reply@eec-bhdms.local',
                    'description' => 'From address used for notifications and password mail.',
                    'rules' => ['required', 'email', 'max:150'],
                ],
            ],
        ],

        'geography' => [
            'label' => 'Geographic configuration',
            'description' => 'Where the map opens and which basemap it starts with.',
            'settings' => [
                'map_center_lat' => [
                    'label' => 'Map centre latitude',
                    'type' => 'decimal',
                    'config' => 'eec.map.center.lat',
                    'default' => 9.145,
                    'description' => 'WGS84 latitude in decimal degrees.',
                    'rules' => ['required', 'numeric', 'between:-90,90'],
                ],
                'map_center_lng' => [
                    'label' => 'Map centre longitude',
                    'type' => 'decimal',
                    'config' => 'eec.map.center.lng',
                    'default' => 40.489673,
                    'description' => 'WGS84 longitude in decimal degrees.',
                    'rules' => ['required', 'numeric', 'between:-180,180'],
                ],
                'map_zoom' => [
                    'label' => 'Opening zoom',
                    'type' => 'integer',
                    'config' => 'eec.map.zoom',
                    'default' => 6,
                    'description' => 'Initial zoom level, between the configured minimum and maximum.',
                    'rules' => ['required', 'integer', 'between:1,18'],
                ],
                'default_basemap' => [
                    'label' => 'Default basemap',
                    'type' => 'select',
                    'config' => 'gis.default_basemap',
                    'default' => 'terrain',
                    'description' => 'Style used when the map first opens.',
                    'options' => ['terrain', 'imagery', 'streets'],
                    'rules' => ['required', 'in:terrain,imagery,streets'],
                ],
            ],
        ],

        'imports' => [
            'label' => 'Import settings',
            'description' => 'Upload limits applied to workbook inspection and chunked import.',
            'settings' => [
                'max_upload_kb' => [
                    'label' => 'Maximum upload size (KB)',
                    'type' => 'integer',
                    'config' => 'excel.limits.max_upload_kb',
                    'default' => 30720,
                    'description' => 'Largest workbook accepted by the upload form.',
                    'rules' => ['required', 'integer', 'between:1024,102400'],
                ],
                'preview_rows' => [
                    'label' => 'Preview rows',
                    'type' => 'integer',
                    'config' => 'excel.limits.preview_rows',
                    'default' => 40,
                    'description' => 'Rows returned by the worksheet preview.',
                    'rules' => ['required', 'integer', 'between:10,200'],
                ],
                'import_chunk' => [
                    'label' => 'Import chunk size',
                    'type' => 'integer',
                    'config' => 'excel.limits.import_chunk',
                    'default' => 500,
                    'description' => 'Rows written per transaction during import.',
                    'rules' => ['required', 'integer', 'between:100,5000'],
                ],
            ],
        ],

        'quality' => [
            'label' => 'Quality thresholds',
            'description' => 'Targets used when a report judges coverage and completeness.',
            'settings' => [
                'approved_target_percent' => [
                    'label' => 'Approved target (%)',
                    'type' => 'decimal',
                    'config' => 'quality.approved_target_percent',
                    'default' => 95,
                    'description' => 'Coverage above this share is reported as healthy.',
                    'rules' => ['required', 'numeric', 'between:50,100'],
                ],
                'missing_warning_percent' => [
                    'label' => 'Missing-data warning (%)',
                    'type' => 'decimal',
                    'config' => 'quality.missing_warning_percent',
                    'default' => 20,
                    'description' => 'Gaps larger than this share of the span are flagged in reports.',
                    'rules' => ['required', 'numeric', 'between:0,100'],
                ],
            ],
        ],
    ],
];
