<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementUnit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'dimension',
        'decimals',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class, 'default_unit_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(HydrologicalObservation::class);
    }

    public function stationParameters(): HasMany
    {
        return $this->hasMany(StationParameter::class);
    }
}
