<?php

/**
 * Backup policy.
 *
 * The database dump is the irreplaceable part: uploaded workbooks, imported
 * documents and GIS files are copied alongside it so one restore point contains
 * everything needed to rebuild the system.
 */
return [
    // Destination directory, relative to storage_path().
    'path' => 'app/backups',

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    // Binary used for the logical dump. XAMPP ships mysqldump next to mysql.
    'mysqldump' => env('DB_DUMP_BINARY', 'mysqldump'),

    // Directories (relative to storage_path) that are archived with the dump.
    'paths' => [
        'app/private',
        'app/public',
    ],

    'schedule' => [
        'enabled' => (bool) env('BACKUP_ENABLED', true),
        'time' => env('BACKUP_TIME', '01:30'),
    ],
];
