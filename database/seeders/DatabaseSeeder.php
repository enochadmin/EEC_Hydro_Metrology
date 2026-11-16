<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: units before parameters (parameters reference a default
     * unit), roles before users (accounts are assigned a role).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            MeasurementUnitSeeder::class,
            ParameterSeeder::class,
            BasinSeeder::class,
            SubBasinSeeder::class,
            StationCategorySeeder::class,
            MonitoringStationSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(UserSeeder::class);
        }
    }
}
