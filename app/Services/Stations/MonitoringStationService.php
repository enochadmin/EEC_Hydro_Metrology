<?php

namespace App\Services\Stations;

use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\StationParameter;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MonitoringStationService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): MonitoringStation
    {
        return DB::transaction(function () use ($attributes, $actor): MonitoringStation {
            $parameterIds = $this->parameterIds($attributes);
            $station = MonitoringStation::create($this->normalise($attributes));

            $this->storeLocation($station);
            $this->logParameterChanges($station, $parameterIds, $actor);

            return $station->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(MonitoringStation $station, array $attributes, User $actor): MonitoringStation
    {
        return DB::transaction(function () use ($station, $attributes, $actor): MonitoringStation {
            $parameterIds = $this->parameterIds($attributes);
            $station->fill($this->normalise($attributes));
            $station->save();

            $this->storeLocation($station);
            $this->logParameterChanges($station, $parameterIds, $actor);

            return $station->refresh();
        });
    }

    public function delete(MonitoringStation $station): void
    {
        $station->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>
     */
    private function parameterIds(array &$attributes): array
    {
        $parameterIds = array_values(array_unique(array_map('intval', $attributes['parameters'] ?? [])));
        unset($attributes['parameters']);

        return $parameterIds;
    }

    /**
     * @param  list<int>  $parameterIds
     * @return array<int, array<string, mixed>>
     */
    private function parameterPivot(array $parameterIds, ?MonitoringStation $station = null): array
    {
        $existing = $station?->stationParameters()
            ->whereIn('parameter_id', $parameterIds)
            ->get()
            ->keyBy('parameter_id') ?? collect();
        $parameters = Parameter::query()
            ->whereIn('id', $parameterIds)
            ->get(['id', 'default_unit_id'])
            ->keyBy('id');
        $pivot = [];

        foreach ($parameterIds as $parameterId) {
            $stationParameter = $existing->get($parameterId);

            $pivot[$parameterId] = $stationParameter instanceof StationParameter
                ? $stationParameter->only([
                    'measurement_unit_id',
                    'sensor_reference',
                    'depth_m',
                    'starts_on',
                    'ends_on',
                    'is_active',
                    'notes',
                ])
                : [
                    'measurement_unit_id' => $parameters->get($parameterId)?->default_unit_id,
                    'is_active' => true,
                ];
        }

        return $pivot;
    }

    /**
     * @param  list<int>  $parameterIds
     */
    private function logParameterChanges(MonitoringStation $station, array $parameterIds, User $actor): void
    {
        $changes = $station->parameters()->sync($this->parameterPivot($parameterIds, $station));

        if ($changes['attached'] === [] && $changes['detached'] === [] && $changes['updated'] === []) {
            return;
        }

        $this->logger->log(
            'monitoring_station.parameters_updated',
            $station,
            'Parameters updated for station '.$station->code.'.',
            [
                'attached' => array_values($changes['attached']),
                'detached' => array_values($changes['detached']),
            ],
            $actor,
        );
    }

    private function storeLocation(MonitoringStation $station): void
    {
        if ($station->latitude === null || $station->longitude === null) {
            $station->setGeometryWkt('location', null);

            return;
        }

        $station->setGeometryWkt(
            'location',
            sprintf('POINT(%s %s)', $station->longitude, $station->latitude),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $attributes['code'] = Str::upper(trim((string) $attributes['code']));

        return $attributes;
    }
}
