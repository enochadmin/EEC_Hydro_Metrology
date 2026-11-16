<?php

/**
 * Single source of truth for EEC-BHDMS roles and permissions.
 *
 * The seeders read this file, the roles screen renders from it, and the
 * Super Admin bypass in AppServiceProvider references `super_admin_role`.
 * Permission strings are the exact values stored by spatie/laravel-permission.
 */
return [

    'super_admin_role' => 'Super Admin',

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    | Key = role name, value = responsibility shown in the access-control UI.
    */

    'roles' => [
        'Super Admin' => 'Unrestricted access to every module and setting.',
        'System Administrator' => 'Manages users, roles and application configuration.',
        'Basin Manager' => 'Maintains basin, sub-basin and boundary records.',
        'GIS Officer' => 'Maintains map layers and geographic boundaries.',
        'Data Manager' => 'Handles station records, Excel uploads and data quality.',
        'Data Analyst' => 'Reviews datasets and produces reports.',
        'Viewer' => 'Read-only access to published information.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission catalogue
    |--------------------------------------------------------------------------
    | Grouped by module so the matrix screen stays readable. Every entry is a
    | Spatie permission name; grouping is presentation only.
    */

    'permissions' => [
        'Dashboard' => [
            'view dashboard',
        ],
        'Basin' => [
            'view basins',
            'create basins',
            'edit basins',
            'delete basins',
            'import basin boundaries',
        ],
        'Sub-basin' => [
            'view sub-basins',
            'create sub-basins',
            'edit sub-basins',
            'delete sub-basins',
        ],
        'GIS' => [
            'view map',
            'manage map layers',
            'manage geographic boundaries',
        ],
        'Monitoring Stations' => [
            'view stations',
            'create stations',
            'edit stations',
            'delete stations',
        ],
        'Hydrological Data' => [
            'view data',
            'upload data',
            'transform data',
            'approve data',
            'delete data',
            'export data',
        ],
        'Reports' => [
            'view reports',
            'generate reports',
            'export reports',
            'export restricted reports',
        ],
        'Users' => [
            'view users',
            'create users',
            'edit users',
            'deactivate users',
            'assign roles',
        ],
        'System' => [
            'manage settings',
            'view activity logs',
            'manage permissions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role → permission mapping
    |--------------------------------------------------------------------------
    | Super Admin receives every permission through Gate::before and is also
    | synced with the full catalogue so the matrix shows its true scope.
    */

    'role_permissions' => [
        'Super Admin' => ['*'],

        'System Administrator' => [
            'view dashboard',
            'view basins',
            'view sub-basins',
            'view map',
            'view stations',
            'view data',
            'view reports',
            'view users',
            'create users',
            'edit users',
            'deactivate users',
            'assign roles',
            'manage settings',
            'view activity logs',
            'manage permissions',
            'export restricted reports',
        ],

        'Basin Manager' => [
            'view dashboard',
            'view basins',
            'create basins',
            'edit basins',
            'delete basins',
            'import basin boundaries',
            'view sub-basins',
            'create sub-basins',
            'edit sub-basins',
            'delete sub-basins',
            'view map',
            'view stations',
            'view data',
            'view reports',
        ],

        'GIS Officer' => [
            'view dashboard',
            'view basins',
            'view sub-basins',
            'import basin boundaries',
            'view map',
            'manage map layers',
            'manage geographic boundaries',
            'view stations',
        ],

        'Data Manager' => [
            'view dashboard',
            'view basins',
            'view sub-basins',
            'view map',
            'view stations',
            'create stations',
            'edit stations',
            'view data',
            'upload data',
            'transform data',
            'approve data',
            'delete data',
            'export data',
            'view reports',
            'generate reports',
            'export restricted reports',
        ],

        'Data Analyst' => [
            'view dashboard',
            'view basins',
            'view sub-basins',
            'view map',
            'view stations',
            'view data',
            'export data',
            'view reports',
            'generate reports',
            'export reports',
            'export restricted reports',
        ],

        'Viewer' => [
            'view dashboard',
            'view basins',
            'view sub-basins',
            'view map',
            'view stations',
            'view data',
            'view reports',
        ],
    ],

];
