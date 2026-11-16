<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provenance of geographic records, observations and import batches.
 */
class DataSource extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'organisation',
        'contact_email',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function basins(): HasMany
    {
        return $this->hasMany(Basin::class);
    }

    public function boundaries(): HasMany
    {
        return $this->hasMany(BasinBoundary::class, 'source_id');
    }

    public function stations(): HasMany
    {
        return $this->hasMany(MonitoringStation::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(HydrologicalObservation::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(DataImportBatch::class);
    }
}
