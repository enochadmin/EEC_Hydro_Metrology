<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Display metadata for a station type. `code` must match one of the values in
 * the monitoring_stations.station_type enum, which the seeder keeps in step.
 */
class StationCategory extends Model
{
    protected $fillable = [
        'code',
        'label',
        'description',
        'colour',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
