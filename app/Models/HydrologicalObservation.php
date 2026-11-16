<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One measured value for a station, parameter and timestamp.
 *
 * `series_no` disambiguates legitimate multiple readings at the same instant
 * (replicate gaugings, co-located sensors); the unique index on
 * (station, parameter, measured_at, series_no) rejects accidental duplicates.
 */
class HydrologicalObservation extends Model
{
    protected $fillable = [
        'monitoring_station_id',
        'parameter_id',
        'measurement_unit_id',
        'measured_at',
        'value',
        'series_no',
        'record_type',
        'quality_status',
        'data_source_id',
        'data_import_batch_id',
        'import_row_number',
        'entered_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'value' => 'decimal:6',
            'series_no' => 'integer',
            'import_row_number' => 'integer',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(MonitoringStation::class, 'monitoring_station_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(DataImportBatch::class, 'data_import_batch_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function qualityIssues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
    }

    /**
     * @param  Builder<HydrologicalObservation>  $query
     * @return Builder<HydrologicalObservation>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('quality_status', 'approved');
    }

    /**
     * @param  Builder<HydrologicalObservation>  $query
     * @return Builder<HydrologicalObservation>
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->whereIn('quality_status', ['pending', 'flagged']);
    }
}
