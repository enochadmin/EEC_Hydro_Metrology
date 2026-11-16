<?php

namespace Database\Seeders;

use App\Models\Basin;
use App\Models\SubBasin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubBasinSeeder extends Seeder
{
    /**
     * @var list<array{
     *     basin_code: string,
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
    private const SUB_BASINS = [
        // Abbay
        [
            'basin_code' => 'BH-01',
            'code' => 'SB-ABB-01',
            'name' => 'Lake Tana Sub-Basin',
            'name_alt' => 'Tana Catchment',
            'area_km2' => 15320.00,
            'centroid_lat' => 11.9500000,
            'centroid_lng' => 37.3500000,
            'elevation_m' => 1800.00,
            'boundary_wkt' => 'POLYGON((36.8 12.5, 37.8 12.5, 37.8 11.4, 36.8 11.4, 36.8 12.5))',
            'description' => 'Source of the Blue Nile River including Lake Tana, Gilgel Abbay, Ribb, and Gumara river catchments.',
        ],
        [
            'basin_code' => 'BH-01',
            'code' => 'SB-ABB-02',
            'name' => 'Didessa Sub-Basin',
            'name_alt' => null,
            'area_km2' => 19630.00,
            'centroid_lat' => 8.9500000,
            'centroid_lng' => 36.4500000,
            'elevation_m' => 1650.00,
            'boundary_wkt' => 'POLYGON((36.0 9.8, 37.0 9.8, 37.0 8.2, 36.0 8.2, 36.0 9.8))',
            'description' => 'Largest tributary to the Blue Nile in terms of discharge volume, flowing north from Wollega.',
        ],
        [
            'basin_code' => 'BH-01',
            'code' => 'SB-ABB-03',
            'name' => 'Beles Sub-Basin',
            'name_alt' => null,
            'area_km2' => 14200.00,
            'centroid_lat' => 11.2000000,
            'centroid_lng' => 36.3000000,
            'elevation_m' => 1200.00,
            'boundary_wkt' => 'POLYGON((35.8 11.8, 36.8 11.8, 36.8 10.6, 35.8 10.6, 35.8 11.8))',
            'description' => 'Receives diverted water from Lake Tana through the Tana-Beles hydroelectric plant.',
        ],
        [
            'basin_code' => 'BH-01',
            'code' => 'SB-ABB-04',
            'name' => 'Finchaa Sub-Basin',
            'name_alt' => null,
            'area_km2' => 4080.00,
            'centroid_lat' => 9.6000000,
            'centroid_lng' => 37.4000000,
            'elevation_m' => 2200.00,
            'boundary_wkt' => 'POLYGON((37.1 9.9, 37.7 9.9, 37.7 9.3, 37.1 9.3, 37.1 9.9))',
            'description' => 'Highland reservoir catchment feeding the Finchaa hydroelectric scheme and sugar plantation.',
        ],

        // Awash
        [
            'basin_code' => 'BH-02',
            'code' => 'SB-AWA-01',
            'name' => 'Upper Awash Sub-Basin',
            'name_alt' => 'Hombole / Koka',
            'area_km2' => 11400.00,
            'centroid_lat' => 8.7000000,
            'centroid_lng' => 38.6000000,
            'elevation_m' => 2100.00,
            'boundary_wkt' => 'POLYGON((38.0 9.2, 39.2 9.2, 39.2 8.3, 38.0 8.3, 38.0 9.2))',
            'description' => 'Encompasses Addis Ababa and surrounding highlands draining south into Lake Koka.',
        ],
        [
            'basin_code' => 'BH-02',
            'code' => 'SB-AWA-02',
            'name' => 'Middle Awash Sub-Basin',
            'name_alt' => 'Metehara / Awash Gorge',
            'area_km2' => 24500.00,
            'centroid_lat' => 9.2000000,
            'centroid_lng' => 40.1000000,
            'elevation_m' => 950.00,
            'boundary_wkt' => 'POLYGON((39.4 9.8, 40.8 9.8, 40.8 8.6, 39.4 8.6, 39.4 9.8))',
            'description' => 'River section featuring Metehara sugarcane estates, Fantale canal, and Awash National Park.',
        ],
        [
            'basin_code' => 'BH-02',
            'code' => 'SB-AWA-03',
            'name' => 'Lower Awash Sub-Basin',
            'name_alt' => 'Tendaho / Asaita',
            'area_km2' => 36200.00,
            'centroid_lat' => 11.4000000,
            'centroid_lng' => 41.2000000,
            'elevation_m' => 450.00,
            'boundary_wkt' => 'POLYGON((40.5 12.0, 41.8 12.0, 41.8 10.8, 40.5 10.8, 40.5 12.0))',
            'description' => 'Arid terminal wetlands and lakes in Afar, terminating at Lake Abbe.',
        ],

        // Baro-Akobo
        [
            'basin_code' => 'BH-03',
            'code' => 'SB-BAR-01',
            'name' => 'Baro Sub-Basin',
            'name_alt' => null,
            'area_km2' => 41400.00,
            'centroid_lat' => 8.2500000,
            'centroid_lng' => 34.6000000,
            'elevation_m' => 600.00,
            'boundary_wkt' => 'POLYGON((33.5 8.8, 35.5 8.8, 35.5 7.8, 33.5 7.8, 33.5 8.8))',
            'description' => 'Baro River mainstem draining Gambella lowlands toward South Sudan.',
        ],
        [
            'basin_code' => 'BH-03',
            'code' => 'SB-BAR-02',
            'name' => 'Akobo Sub-Basin',
            'name_alt' => null,
            'area_km2' => 18200.00,
            'centroid_lat' => 7.3000000,
            'centroid_lng' => 34.8000000,
            'elevation_m' => 550.00,
            'boundary_wkt' => 'POLYGON((34.0 7.8, 35.4 7.8, 35.4 6.8, 34.0 6.8, 34.0 7.8))',
            'description' => 'Southern catchment bordering South Sudan, draining Akobo and Pibor tributaries.',
        ],

        // Tekeze
        [
            'basin_code' => 'BH-04',
            'code' => 'SB-TEK-01',
            'name' => 'Upper Tekeze Sub-Basin',
            'name_alt' => null,
            'area_km2' => 45600.00,
            'centroid_lat' => 13.0000000,
            'centroid_lng' => 39.0000000,
            'elevation_m' => 1900.00,
            'boundary_wkt' => 'POLYGON((38.0 13.8, 39.8 13.8, 39.8 12.2, 38.0 12.2, 38.0 13.8))',
            'description' => 'Highland source areas in Lasta, North Wollo, and central Tigray feeding Tekeze Dam.',
        ],
        [
            'basin_code' => 'BH-04',
            'code' => 'SB-TEK-02',
            'name' => 'Lower Tekeze / Humera Sub-Basin',
            'name_alt' => 'Setit Border',
            'area_km2' => 27510.00,
            'centroid_lat' => 13.9000000,
            'centroid_lng' => 37.1000000,
            'elevation_m' => 680.00,
            'boundary_wkt' => 'POLYGON((36.4 14.4, 37.8 14.4, 37.8 13.4, 36.4 13.4, 36.4 14.4))',
            'description' => 'Northwestern agricultural lowlands near Humera where Tekeze enters Sudan as the Setit.',
        ],

        // Omo-Gibe
        [
            'basin_code' => 'BH-05',
            'code' => 'SB-OMO-01',
            'name' => 'Upper Gibe Sub-Basin',
            'name_alt' => 'Gibe I & II',
            'area_km2' => 18100.00,
            'centroid_lat' => 8.2000000,
            'centroid_lng' => 37.4000000,
            'elevation_m' => 1700.00,
            'boundary_wkt' => 'POLYGON((36.9 8.8, 37.8 8.8, 37.8 7.6, 36.9 7.6, 36.9 8.8))',
            'description' => 'Gibe River headwaters in Shewa and Jimma plateau, feeding Gibe I and II hydroelectric schemes.',
        ],
        [
            'basin_code' => 'BH-05',
            'code' => 'SB-OMO-02',
            'name' => 'Lower Omo Sub-Basin',
            'name_alt' => 'Lake Turkana Outlet',
            'area_km2' => 46600.00,
            'centroid_lat' => 5.5000000,
            'centroid_lng' => 36.4000000,
            'elevation_m' => 500.00,
            'boundary_wkt' => 'POLYGON((35.8 6.5, 37.0 6.5, 37.0 4.5, 35.8 4.5, 35.8 6.5))',
            'description' => 'Downstream of Gibe III Dam, flowing through South Omo valley to Lake Turkana delta.',
        ],

        // Rift Valley Lakes
        [
            'basin_code' => 'BH-08',
            'code' => 'SB-RVL-01',
            'name' => 'Ziway-Shala Sub-Basin',
            'name_alt' => 'Northern Rift Lakes',
            'area_km2' => 14600.00,
            'centroid_lat' => 7.6000000,
            'centroid_lng' => 38.7000000,
            'elevation_m' => 1640.00,
            'boundary_wkt' => 'POLYGON((38.3 8.2, 39.1 8.2, 39.1 7.2, 38.3 7.2, 38.3 8.2))',
            'description' => 'Hydrologically interconnected lakes Ziway, Langano, Abijatta, and Shala in the Main Ethiopian Rift.',
        ],
        [
            'basin_code' => 'BH-08',
            'code' => 'SB-RVL-02',
            'name' => 'Lake Hawassa Sub-Basin',
            'name_alt' => 'Hawassa Caldera',
            'area_km2' => 1440.00,
            'centroid_lat' => 7.0500000,
            'centroid_lng' => 38.5000000,
            'elevation_m' => 1710.00,
            'boundary_wkt' => 'POLYGON((38.35 7.18, 38.65 7.18, 38.65 6.95, 38.35 6.95, 38.35 7.18))',
            'description' => 'Closed caldera basin of Lake Hawassa with intensive urban and agricultural water demands.',
        ],
        [
            'basin_code' => 'BH-08',
            'code' => 'SB-RVL-03',
            'name' => 'Lake Abaya-Chamo Sub-Basin',
            'name_alt' => 'Southern Rift Lakes',
            'area_km2' => 18100.00,
            'centroid_lat' => 6.1000000,
            'centroid_lng' => 37.7000000,
            'elevation_m' => 1250.00,
            'boundary_wkt' => 'POLYGON((37.2 6.8, 38.2 6.8, 38.2 5.5, 37.2 5.5, 37.2 6.8))',
            'description' => 'Large southern rift lakes Abaya and Chamo, fed by the Bilate, Hare, and Kulfo rivers near Arba Minch.',
        ],

        // Genale-Dawa
        [
            'basin_code' => 'BH-06',
            'code' => 'SB-GEN-01',
            'name' => 'Genale Sub-Basin',
            'name_alt' => null,
            'area_km2' => 57200.00,
            'centroid_lat' => 5.8000000,
            'centroid_lng' => 40.5000000,
            'elevation_m' => 1200.00,
            'boundary_wkt' => 'POLYGON((39.5 6.8, 41.5 6.8, 41.5 5.0, 39.5 5.0, 39.5 6.8))',
            'description' => 'Genale River system draining from the Bale mountains, site of the Genale Dawa III hydropower project.',
        ],
        [
            'basin_code' => 'BH-06',
            'code' => 'SB-GEN-02',
            'name' => 'Dawa Sub-Basin',
            'name_alt' => null,
            'area_km2' => 59100.00,
            'centroid_lat' => 4.8000000,
            'centroid_lng' => 39.8000000,
            'elevation_m' => 950.00,
            'boundary_wkt' => 'POLYGON((38.8 5.6, 40.8 5.6, 40.8 4.0, 38.8 4.0, 38.8 5.6))',
            'description' => 'Dawa River flowing through Borana pastoral lowlands toward the border at Dolo Odo.',
        ],

        // Wabi Shebelle
        [
            'basin_code' => 'BH-07',
            'code' => 'SB-WAB-01',
            'name' => 'Upper Wabi Shebelle Sub-Basin',
            'name_alt' => null,
            'area_km2' => 38200.00,
            'centroid_lat' => 7.5000000,
            'centroid_lng' => 40.2000000,
            'elevation_m' => 2200.00,
            'boundary_wkt' => 'POLYGON((39.2 8.2, 41.2 8.2, 41.2 6.8, 39.2 6.8, 39.2 8.2))',
            'description' => 'Headwater streams originating in the Arsi and Bale mountain ranges.',
        ],
        [
            'basin_code' => 'BH-07',
            'code' => 'SB-WAB-02',
            'name' => 'Middle Shebelle / Gode Sub-Basin',
            'name_alt' => null,
            'area_km2' => 71500.00,
            'centroid_lat' => 6.5000000,
            'centroid_lng' => 43.2000000,
            'elevation_m' => 350.00,
            'boundary_wkt' => 'POLYGON((42.0 7.5, 44.5 7.5, 44.5 5.5, 42.0 5.5, 42.0 7.5))',
            'description' => 'Arid river plain passing Gode with major irrigated agriculture schemes.',
        ],

        // Afar-Danakil
        [
            'basin_code' => 'BH-09',
            'code' => 'SB-DAN-01',
            'name' => 'Danakil Depression Sub-Basin',
            'name_alt' => 'Dallol Depression',
            'area_km2' => 42000.00,
            'centroid_lat' => 13.8000000,
            'centroid_lng' => 40.6000000,
            'elevation_m' => -80.00,
            'boundary_wkt' => 'POLYGON((39.9 14.6, 41.2 14.6, 41.2 12.8, 39.9 12.8, 39.9 14.6))',
            'description' => 'Hyper-arid geological graben below sea level with thermal springs and salt pans.',
        ],

        // Aysha
        [
            'basin_code' => 'BH-10',
            'code' => 'SB-AYS-01',
            'name' => 'Aysha Catchment',
            'name_alt' => null,
            'area_km2' => 2223.00,
            'centroid_lat' => 10.8000000,
            'centroid_lng' => 42.6000000,
            'elevation_m' => 720.00,
            'boundary_wkt' => 'POLYGON((42.1 11.1, 42.9 11.1, 42.9 10.5, 42.1 10.5, 42.1 11.1))',
            'description' => 'Intermittent wadis in the Ethio-Djibouti corridor.',
        ],

        // Ogaden
        [
            'basin_code' => 'BH-11',
            'code' => 'SB-OGA-01',
            'name' => 'Ogaden Plains Catchment',
            'name_alt' => null,
            'area_km2' => 77100.00,
            'centroid_lat' => 6.8000000,
            'centroid_lng' => 45.2000000,
            'elevation_m' => 580.00,
            'boundary_wkt' => 'POLYGON((44.6 7.8, 47.5 7.8, 47.5 5.8, 44.6 5.8, 44.6 7.8))',
            'description' => 'Expansive arid plains of the Somali Region with seasonal ephemeral drainage.',
        ],

        // Mereb
        [
            'basin_code' => 'BH-12',
            'code' => 'SB-MER-01',
            'name' => 'Upper Mereb Catchment',
            'name_alt' => null,
            'area_km2' => 5900.00,
            'centroid_lat' => 14.5000000,
            'centroid_lng' => 38.5000000,
            'elevation_m' => 1480.00,
            'boundary_wkt' => 'POLYGON((37.9 14.8, 39.1 14.8, 39.1 14.2, 37.9 14.2, 37.9 14.8))',
            'description' => 'Border catchment draining the northern Tigray plateau into the Mereb River.',
        ],
    ];

    public function run(): void
    {
        $basins = Basin::query()->pluck('id', 'code');
        $order = 0;

        foreach (self::SUB_BASINS as $item) {
            $basinId = $basins->get($item['basin_code']);

            if (! $basinId) {
                continue;
            }

            $order += 10;

            $subBasin = SubBasin::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'basin_id' => $basinId,
                    'name' => $item['name'],
                    'name_alt' => $item['name_alt'],
                    'description' => $item['description'],
                    'area_km2' => $item['area_km2'],
                    'centroid_lat' => $item['centroid_lat'],
                    'centroid_lng' => $item['centroid_lng'],
                    'elevation_m' => $item['elevation_m'],
                    'data_status' => 'verified',
                    'is_active' => true,
                    'sort_order' => $order,
                ],
            );

            DB::update(
                'UPDATE sub_basins SET boundary = ST_GeomFromText(?, 4326) WHERE id = ?',
                [$item['boundary_wkt'], $subBasin->id],
            );
        }

        $this->command?->info(sprintf(
            'Seeded %d Ethiopian sub-basins with geometry and coordinates.',
            count(self::SUB_BASINS),
        ));
    }
}
