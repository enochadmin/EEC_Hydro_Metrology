<?php

namespace Database\Factories;

use App\Models\Basin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Basin>
 */
class BasinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TST-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true).' Basin',
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

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'data_status' => 'verified',
            'area_km2' => 12500.50,
            'centroid_lat' => 9.1450000,
            'centroid_lng' => 40.4896730,
            'elevation_m' => 1450.00,
        ]);
    }
}
