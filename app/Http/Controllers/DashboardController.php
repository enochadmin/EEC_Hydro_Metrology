<?php

namespace App\Http\Controllers;

use App\Models\Basin;
use App\Models\HydrologicalObservation;
use App\Models\MonitoringStation;
use App\Models\SubBasin;
use App\Services\SystemStatusService;
use App\Support\Gis\BasemapRegistry;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SystemStatusService $systemStatus, BasemapRegistry $basemaps): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $this->stats(),
            'modules' => config('eec.modules'),
            'systemStatus' => $systemStatus->snapshot(),
            'basemap' => $basemaps->default(),
        ]);
    }

    /**
     * Headline counts, read from the tables that own them.
     *
     * @return list<array{key: string, label: string, value: int}>
     */
    private function stats(): array
    {
        return [
            [
                'key' => 'basins',
                'label' => 'Basins',
                'value' => Basin::query()->count(),
            ],
            [
                'key' => 'sub_basins',
                'label' => 'Sub-basins',
                'value' => SubBasin::query()->count(),
            ],
            [
                'key' => 'stations',
                'label' => 'Stations',
                'value' => MonitoringStation::query()->count(),
            ],
            [
                'key' => 'records',
                'label' => 'Observations',
                'value' => HydrologicalObservation::query()->count(),
            ],
        ];
    }
}
