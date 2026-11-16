<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;

/**
 * Canonical units referenced by parameters and observations.
 */
class MeasurementUnitSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, symbol: string, dimension: string, decimals: int}>
     */
    private const UNITS = [
        ['code' => 'mm', 'name' => 'Millimetre', 'symbol' => 'mm', 'dimension' => 'length', 'decimals' => 1],
        ['code' => 'm', 'name' => 'Metre', 'symbol' => 'm', 'dimension' => 'length', 'decimals' => 2],
        ['code' => 'degc', 'name' => 'Degree Celsius', 'symbol' => '°C', 'dimension' => 'temperature', 'decimals' => 1],
        ['code' => 'pct', 'name' => 'Percent', 'symbol' => '%', 'dimension' => 'ratio', 'decimals' => 1],
        ['code' => 'mps', 'name' => 'Metre per second', 'symbol' => 'm/s', 'dimension' => 'speed', 'decimals' => 1],
        ['code' => 'hr', 'name' => 'Hour', 'symbol' => 'h', 'dimension' => 'time', 'decimals' => 1],
        ['code' => 'm3s', 'name' => 'Cubic metre per second', 'symbol' => 'm³/s', 'dimension' => 'volumetric_flow', 'decimals' => 3],
    ];

    public function run(): void
    {
        foreach (self::UNITS as $unit) {
            MeasurementUnit::query()->updateOrCreate(
                ['code' => $unit['code']],
                $unit + ['is_active' => true],
            );
        }

        $this->command?->info(sprintf('Measurement units seeded: %d.', count(self::UNITS)));
    }
}
