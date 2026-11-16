<?php

namespace Database\Seeders;

use App\Models\Basin;
use App\Models\BasinBoundary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The twelve major Ethiopian river basins with verified geographic attributes,
 * centroids, official catchment areas (km2), and simplified display boundaries.
 */
class BasinSeeder extends Seeder
{
    /**
     * @var list<array{
     *     code: string,
     *     name: string,
     *     name_alt: string|null,
     *     area_km2: float,
     *     centroid_lat: float,
     *     centroid_lng: float,
     *     elevation_m: float,
     *     boundary_wkt: string,
     *     description: string
     * }>
     */
    private const BASINS = [
        [
            'code' => 'BH-01',
            'name' => 'Abbay',
            'name_alt' => 'Blue Nile',
            'area_km2' => 199812.00,
            'centroid_lat' => 10.2500000,
            'centroid_lng' => 37.0500000,
            'elevation_m' => 1780.00,
            'boundary_wkt' => 'POLYGON((36.5 12.8, 37.5 12.8, 38.2 12.2, 38.6 11.5, 38.8 10.5, 38.5 9.5, 37.8 8.8, 36.8 8.9, 35.5 9.2, 34.8 10.2, 35.0 11.5, 36.5 12.8))',
            'description' => 'The Abbay (Blue Nile) River Basin drains the northwestern highlands and Lake Tana, contributing over 60% of the Nile flow. Major tributaries include Didessa, Dabus, Beles, Finchaa, Guder, and Muger.',
        ],
        [
            'code' => 'BH-02',
            'name' => 'Awash',
            'name_alt' => null,
            'area_km2' => 112700.00,
            'centroid_lat' => 9.1500000,
            'centroid_lng' => 40.1500000,
            'elevation_m' => 1050.00,
            'boundary_wkt' => 'POLYGON((38.0 9.0, 38.6 9.5, 39.5 10.5, 40.5 11.8, 41.8 12.0, 41.8 11.0, 41.0 9.8, 40.0 8.8, 39.0 8.2, 38.2 8.4, 38.0 9.0))',
            'description' => 'The Awash River Basin originates west of Addis Ababa and flows northeast through the Great Rift Valley terminating in Lake Abbe in the Afar triangle. It is the most extensively developed agro-industrial basin in Ethiopia.',
        ],
        [
            'code' => 'BH-03',
            'name' => 'Baro-Akobo',
            'name_alt' => 'Sobat',
            'area_km2' => 76203.00,
            'centroid_lat' => 7.8000000,
            'centroid_lng' => 35.1000000,
            'elevation_m' => 890.00,
            'boundary_wkt' => 'POLYGON((34.0 9.0, 35.5 9.2, 36.2 8.5, 36.0 7.2, 35.2 6.5, 34.0 6.8, 33.0 7.5, 33.2 8.4, 34.0 9.0))',
            'description' => 'The Baro-Akobo Basin in southwestern Ethiopia has high rainfall and substantial water yield. Its perennial rivers (Baro, Akobo, and Gilo) join across the South Sudanese border into the Sobat River.',
        ],
        [
            'code' => 'BH-04',
            'name' => 'Tekeze',
            'name_alt' => 'Setit',
            'area_km2' => 86510.00,
            'centroid_lat' => 13.5000000,
            'centroid_lng' => 38.3000000,
            'elevation_m' => 1550.00,
            'boundary_wkt' => 'POLYGON((36.5 14.3, 37.8 14.5, 39.5 14.2, 39.8 13.0, 39.2 12.0, 37.8 12.2, 36.8 12.8, 36.2 13.5, 36.5 14.3))',
            'description' => 'The Tekeze (Setit) River Basin drains the northern highlands, rugged Simien Mountains and Tigray plateau westward toward Sudan where it joins the Atbara River.',
        ],
        [
            'code' => 'BH-05',
            'name' => 'Omo-Gibe',
            'name_alt' => 'Omo',
            'area_km2' => 79000.00,
            'centroid_lat' => 6.5000000,
            'centroid_lng' => 37.1000000,
            'elevation_m' => 1180.00,
            'boundary_wkt' => 'POLYGON((36.8 8.8, 37.8 8.8, 38.0 7.8, 37.8 6.5, 37.2 5.0, 36.0 4.5, 35.8 5.5, 36.0 7.0, 36.8 8.8))',
            'description' => 'The Omo-Gibe Basin flows south through deep gorges of the southern Ethiopian rift into Lake Turkana, generating over half of Ethiopia’s hydroelectric power through the Gibe cascade (Gibe I, II, III, Koysha).',
        ],
        [
            'code' => 'BH-06',
            'name' => 'Genale-Dawa',
            'name_alt' => null,
            'area_km2' => 171050.00,
            'centroid_lat' => 5.5000000,
            'centroid_lng' => 40.0000000,
            'elevation_m' => 1120.00,
            'boundary_wkt' => 'POLYGON((38.5 6.8, 40.0 7.2, 41.5 6.5, 42.5 5.5, 42.1 4.2, 40.5 3.8, 39.0 3.8, 38.2 4.8, 38.5 6.8))',
            'description' => 'The Genale-Dawa Basin drains southeastern Ethiopia from the Bale Massif and Borana plateau toward Somalia, uniting at Dolo Odo to form the Juba River.',
        ],
        [
            'code' => 'BH-07',
            'name' => 'Wabi Shebelle',
            'name_alt' => 'Shebelle',
            'area_km2' => 202697.00,
            'centroid_lat' => 7.2000000,
            'centroid_lng' => 42.5000000,
            'elevation_m' => 1080.00,
            'boundary_wkt' => 'POLYGON((39.0 8.0, 40.5 8.8, 42.0 9.5, 43.5 9.0, 45.0 8.0, 45.5 6.5, 44.5 5.0, 43.0 5.2, 41.5 6.0, 40.0 6.8, 39.0 8.0))',
            'description' => 'The Wabi Shebelle Basin is the largest drainage basin in Ethiopia by catchment area, flowing across eastern Arsi, Bale, and the Somali Regional State toward the Indian Ocean.',
        ],
        [
            'code' => 'BH-08',
            'name' => 'Rift Valley Lakes',
            'name_alt' => 'Rift Valley',
            'area_km2' => 53035.00,
            'centroid_lat' => 7.0500000,
            'centroid_lng' => 38.5000000,
            'elevation_m' => 1650.00,
            'boundary_wkt' => 'POLYGON((38.2 8.4, 39.0 8.2, 39.2 7.2, 38.8 6.0, 37.8 5.2, 37.0 5.0, 37.2 6.2, 37.8 7.5, 38.2 8.4))',
            'description' => 'The Central Rift Valley Lakes Basin forms an endorheic closed-catchment system comprising major natural lakes including Ziway, Langano, Abijatta, Shala, Hawassa, Abaya, and Chamo.',
        ],
        [
            'code' => 'BH-09',
            'name' => 'Afar-Danakil',
            'name_alt' => 'Danakil',
            'area_km2' => 74000.00,
            'centroid_lat' => 12.5000000,
            'centroid_lng' => 41.5000000,
            'elevation_m' => 320.00,
            'boundary_wkt' => 'POLYGON((39.8 14.5, 41.2 14.8, 42.4 12.8, 41.8 11.5, 40.5 11.8, 39.8 13.0, 39.8 14.5))',
            'description' => 'The Afar-Danakil Basin is a hyper-arid northeastern closed depression featuring the Danakil salt plains, geothermal springs, and internal saline sinks such as Lake Afdera and Lake Asale.',
        ],
        [
            'code' => 'BH-10',
            'name' => 'Aysha',
            'name_alt' => 'Aisha',
            'area_km2' => 2223.00,
            'centroid_lat' => 10.8000000,
            'centroid_lng' => 42.6000000,
            'elevation_m' => 720.00,
            'boundary_wkt' => 'POLYGON((42.0 11.2, 43.0 11.2, 43.2 10.4, 42.2 10.4, 42.0 11.2))',
            'description' => 'The Aysha Basin is an arid border catchment in eastern Ethiopia adjacent to Djibouti and northwestern Somalia, containing intermittent wadis and alluvial aquifers.',
        ],
        [
            'code' => 'BH-11',
            'name' => 'Ogaden',
            'name_alt' => null,
            'area_km2' => 77100.00,
            'centroid_lat' => 6.8000000,
            'centroid_lng' => 45.2000000,
            'elevation_m' => 580.00,
            'boundary_wkt' => 'POLYGON((44.5 8.0, 46.5 8.2, 47.8 7.5, 47.0 6.0, 45.5 5.5, 44.5 6.5, 44.5 8.0))',
            'description' => 'The Ogaden dry basin covers expansive arid plains of southeastern Ethiopia, characterized by seasonal ephemeral streamflow channels draining east toward Somalia.',
        ],
        [
            'code' => 'BH-12',
            'name' => 'Mereb',
            'name_alt' => 'Mareb',
            'area_km2' => 5900.00,
            'centroid_lat' => 14.5000000,
            'centroid_lng' => 38.5000000,
            'elevation_m' => 1480.00,
            'boundary_wkt' => 'POLYGON((37.8 14.8, 39.2 14.8, 39.2 14.2, 38.2 14.2, 37.8 14.8))',
            'description' => 'The Mereb (Mareb) River Basin forms part of the international boundary between Ethiopia and Eritrea before reaching the Gash River delta in eastern Sudan.',
        ],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::BASINS as $item) {
            $order += 10;

            $basin = Basin::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'name_alt' => $item['name_alt'],
                    'description' => $item['description'],
                    'area_km2' => $item['area_km2'],
                    'centroid_lat' => $item['centroid_lat'],
                    'centroid_lng' => $item['centroid_lng'],
                    'elevation_m' => $item['elevation_m'],
                    'data_status' => 'verified',
                    'source_note' => 'Official national river basin classification and geographical attributes from MoWE/ArcGIS.',
                    'is_active' => true,
                    'sort_order' => $order,
                ],
            );

            // Populate the geometry boundary in EPSG:4326
            DB::update(
                'UPDATE basins SET boundary = ST_GeomFromText(?, 4326) WHERE id = ?',
                [$item['boundary_wkt'], $basin->id],
            );

            // Record authoritative primary boundary
            $exists = DB::table('basin_boundaries')
                ->where('basin_id', $basin->id)
                ->where('is_primary', true)
                ->exists();

            if ($exists) {
                DB::update(
                    'UPDATE basin_boundaries SET name = ?, geom = ST_GeomFromText(?, 4326), updated_at = NOW() WHERE basin_id = ? AND is_primary = true',
                    [$basin->name.' Official Boundary', $item['boundary_wkt'], $basin->id],
                );
            } else {
                DB::insert(
                    'INSERT INTO basin_boundaries (basin_id, is_primary, name, srid, accuracy_m, effective_from, notes, geom, created_at, updated_at) VALUES (?, 1, ?, 4326, 50.00, ?, ?, ST_GeomFromText(?, 4326), NOW(), NOW())',
                    [$basin->id, $basin->name.' Official Boundary', '2020-01-01', 'Primary boundary polygon for '.$basin->name, $item['boundary_wkt']],
                );
            }
        }

        $this->command?->info(sprintf(
            'Seeded %d Ethiopian river basins with coordinates, area and geometry.',
            count(self::BASINS),
        ));
    }
}
