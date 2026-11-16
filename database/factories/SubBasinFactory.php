<?php

namespace Database\Factories;

use App\Models\Basin;
use App\Models\SubBasin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubBasin>
 */
class SubBasinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'basin_id' => Basin::factory(),
            'code' => 'SUB-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true).' Sub-basin',
            'name_alt' => null,
            'description' => null,
            'area_km2' => null,
            'centroid_lat' => null,
            'centroid_lng' => null,
            'elevation_m' => null,
            'data_source_id' => null,
            'data_status' => 'pending',
            'source_note' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
