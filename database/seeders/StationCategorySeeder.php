<?php

namespace Database\Seeders;

use App\Models\StationCategory;
use Illuminate\Database\Seeder;

/**
 * Display metadata for the station types the schema accepts. Safe to re-run:
 * rows are matched on `code`, and an administrator's edits are preserved.
 */
class StationCategorySeeder extends Seeder
{
    /**
     * @var list<array{code: string, label: string, colour: string, description: string}>
     */
    private const CATEGORIES = [
        ['code' => 'river_gauge', 'label' => 'River gauge', 'colour' => '#0f766e', 'description' => 'Water level and discharge at a gauged river section.'],
        ['code' => 'rainfall', 'label' => 'Rainfall', 'colour' => '#0ea5e9', 'description' => 'Precipitation recorded at a rain gauge.'],
        ['code' => 'meteorological', 'label' => 'Meteorological', 'colour' => '#6366f1', 'description' => 'Combined weather observations such as temperature, humidity and wind.'],
        ['code' => 'water_quality', 'label' => 'Water quality', 'colour' => '#a855f7', 'description' => 'Physical and chemical sampling point.'],
        ['code' => 'reservoir', 'label' => 'Reservoir', 'colour' => '#0891b2', 'description' => 'Storage level and release monitoring.'],
        ['code' => 'spring', 'label' => 'Spring', 'colour' => '#65a30d', 'description' => 'Spring discharge monitoring point.'],
        ['code' => 'other', 'label' => 'Other', 'colour' => '#64748b', 'description' => 'A station that does not fit the listed categories.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            StationCategory::firstOrCreate(
                ['code' => $category['code']],
                [
                    'label' => $category['label'],
                    'colour' => $category['colour'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        $this->command?->info('Station categories seeded: '.count(self::CATEGORIES));
    }
}
