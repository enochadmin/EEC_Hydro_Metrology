<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A measurable quantity (rainfall, streamflow, water level…). Rows are seeded
 * from config/parameters.php and can be extended at runtime.
 */
class Parameter extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_alt',
        'symbol',
        'category',
        'default_unit_id',
        'value_type',
        'min_expected',
        'max_expected',
        'decimals',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_expected' => 'decimal:6',
            'max_expected' => 'decimal:6',
            'decimals' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'default_unit_id');
    }

    public function stationParameters(): HasMany
    {
        return $this->hasMany(StationParameter::class);
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(MonitoringStation::class, 'station_parameters')
            ->withPivot(['measurement_unit_id', 'sensor_reference', 'depth_m', 'starts_on', 'ends_on', 'is_active'])
            ->withTimestamps();
    }

    public function observations(): HasMany
    {
        return $this->hasMany(HydrologicalObservation::class);
    }

    public function qualityIssues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
    }

    /**
     * @param  Builder<Parameter>  $query
     * @return Builder<Parameter>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
