<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityIssue extends Model
{
    protected $fillable = [
        'hydrological_observation_id',
        'data_import_batch_id',
        'monitoring_station_id',
        'parameter_id',
        'issue_type',
        'severity',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(HydrologicalObservation::class, 'hydrological_observation_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(DataImportBatch::class, 'data_import_batch_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(MonitoringStation::class, 'monitoring_station_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<DataQualityIssue>  $query
     * @return Builder<DataQualityIssue>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
