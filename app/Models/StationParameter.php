<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationParameter extends Model
{
    protected $fillable = [
        'monitoring_station_id',
        'parameter_id',
        'measurement_unit_id',
        'sensor_reference',
        'depth_m',
        'starts_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'depth_m' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
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
}
