<?php

namespace App\Http\Requests\Stations;

use App\Models\MonitoringStation;

class UpdateMonitoringStationRequest extends MonitoringStationRequest
{
    public function authorize(): bool
    {
        $station = $this->route('station');

        return $station instanceof MonitoringStation
            && ($this->user()?->can('update', $station) ?? false);
    }
}
