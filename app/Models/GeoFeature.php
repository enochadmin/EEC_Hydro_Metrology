<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supplementary map layer feature (national boundary, river, lake).
 *
 * Basins, sub-basins and watersheds stay in their own tables; this model only
 * covers datasets that do not have a registry table of their own.
 */
class GeoFeature extends Model
{
    use SoftDeletes;

    public const LAYER_NATIONAL_BOUNDARY = 'national_boundary';

    public const LAYER_RIVER = 'river';

    public const LAYER_LAKE = 'lake';

    protected $fillable = [
        'layer_type',
        'name',
        'code',
        'geometry_type',
        'properties',
        'min_lng',
        'min_lat',
        'max_lng',
        'max_lat',
        'content_hash',
        'basin_id',
        'data_source_id',
        'created_by',
        'source_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'is_active' => 'boolean',
            'min_lng' => 'decimal:7',
            'min_lat' => 'decimal:7',
            'max_lng' => 'decimal:7',
            'max_lat' => 'decimal:7',
        ];
    }

    public function basin(): BelongsTo
    {
        return $this->belongsTo(Basin::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
