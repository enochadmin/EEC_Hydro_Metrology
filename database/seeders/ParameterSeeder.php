<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use App\Models\Parameter;
use Illuminate\Database\Seeder;

/**
 * Initial measurement catalogue.
 *
 * Parameters live in the database so the application never hardcodes them;
 * additional parameters can be added through the UI without a deployment.
 * Warning thresholds (min/max) stay null until EEC supplies basin-specific
 * plausibility ranges.
 */
class ParameterSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, category: string, unit: string, value_type: string, decimals: int, description: string}>
     */
    private const PARAMETERS = [
        [
            'code' => 'rainfall',
            'name' => 'Rainfall',
            'category' => 'meteorological',
            'unit' => 'mm',
            'value_type' => 'daily_total',
            'decimals' => 1,
            'description' => 'Rainfall depth accumulated over the recording period.',
        ],
        [
            'code' => 'temperature',
            'name' => 'Temperature',
            'category' => 'meteorological',
            'unit' => 'degc',
            'value_type' => 'instantaneous',
            'decimals' => 1,
            'description' => 'Air temperature at the station.',
        ],
        [
            'code' => 'relative_humidity',
            'name' => 'Relative humidity',
            'category' => 'meteorological',
            'unit' => 'pct',
            'value_type' => 'instantaneous',
            'decimals' => 1,
            'description' => 'Relative humidity of the air.',
        ],
        [
            'code' => 'wind_speed',
            'name' => 'Wind speed',
            'category' => 'meteorological',
            'unit' => 'mps',
            'value_type' => 'instantaneous',
            'decimals' => 1,
            'description' => 'Wind speed at the station.',
        ],
        [
            'code' => 'sunshine_duration',
            'name' => 'Sunshine duration',
            'category' => 'meteorological',
            'unit' => 'hr',
            'value_type' => 'daily_total',
            'decimals' => 1,
            'description' => 'Bright sunshine hours recorded during the day.',
        ],
        [
            'code' => 'streamflow',
            'name' => 'Streamflow',
            'category' => 'hydrological',
            'unit' => 'm3s',
            'value_type' => 'instantaneous',
            'decimals' => 3,
            'description' => 'River discharge at the gauging station.',
        ],
        [
            'code' => 'water_level',
            'name' => 'Water level',
            'category' => 'hydrological',
            'unit' => 'm',
            'value_type' => 'instantaneous',
            'decimals' => 2,
            'description' => 'Stage or gauge height at the station.',
        ],
    ];

    public function run(): void
    {
        $units = MeasurementUnit::query()->pluck('id', 'code');
        $order = 0;

        foreach (self::PARAMETERS as $parameter) {
            $order += 10;

            Parameter::query()->updateOrCreate(
                ['code' => $parameter['code']],
                [
                    'name' => $parameter['name'],
                    'category' => $parameter['category'],
                    'default_unit_id' => $units[$parameter['unit']] ?? null,
                    'value_type' => $parameter['value_type'],
                    'decimals' => $parameter['decimals'],
                    'description' => $parameter['description'],
                    'is_active' => true,
                    'sort_order' => $order,
                ],
            );
        }

        $this->command?->info(sprintf('Parameters seeded: %d.', count(self::PARAMETERS)));
    }
}
