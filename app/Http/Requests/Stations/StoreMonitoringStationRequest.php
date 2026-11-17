<?php

namespace App\Http\Requests\Stations;

use App\Models\MonitoringStation;

class StoreMonitoringStationRequest extends MonitoringStationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MonitoringStation::class) ?? false;
    }
}
