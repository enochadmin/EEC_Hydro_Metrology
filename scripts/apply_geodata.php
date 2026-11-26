<?php

use App\Models\Basin;
use App\Models\MonitoringStation;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$data = json_decode(file_get_contents(storage_path('app/geodata/geodata.json')), true);

$distance = function (float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return 2 * $r * asin(sqrt($a));
};

/* ---------------------------------------------------------------- basins */

$normalise = function (string $name): string {
    return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($name)));
};

$aliases = [
    'merebgash' => 'mereb',
    'denakil' => 'afardanakil',
    'wabishebele' => 'wabishebelle',
    'riftvalley' => 'riftvalleylakes',
];

$basinByName = [];
foreach (Basin::all() as $basin) {
    $basinByName[$normalise($basin->name)] = $basin;
}

$resolveBasin = function (?string $name) use ($basinByName, $normalise, $aliases): ?Basin {
    if ($name === null || trim($name) === '') {
        return null;
    }
    $key = $normalise($name);

    return $basinByName[$key] ?? $basinByName[$aliases[$key] ?? ''] ?? null;
};

DB::table('geo_features')->where('layer_type', 'basin_boundary')->delete();

$basinUpdates = 0;
foreach ($data['basins'] as $row) {
    $basin = $resolveBasin($row['name']);
    if (! $basin) {
        echo "basin not found: {$row['name']}\n";
        continue;
    }

    $wktParts = [];
    foreach ($row['rings'] as $ring) {
        $points = array_map(fn ($p) => $p[0].' '.$p[1], $ring);
        if ($points[0] !== end($points)) {
            $points[] = $points[0];
        }
        $wktParts[] = '('.implode(',', $points).')';
    }
    $wkt = 'POLYGON('.implode(',', $wktParts).')';
    $geom = DB::raw("ST_GeomFromText('".$wkt."', 4326)");

    DB::table('basins')->where('id', $basin->id)->update([
        'boundary' => $geom,
        'centroid_lat' => $row['centroid_lat'],
        'centroid_lng' => $row['centroid_lng'],
        'annual_runoff' => $row['runoff'],
        'source_note' => 'Boundary from EMA Ethio_basin shapefile (Adindan UTM 37N, converted to WGS84).',
        'updated_at' => now(),
    ]);

    DB::table('geo_features')->insert([
        'layer_type' => 'basin_boundary',
        'name' => $row['name'],
        'code' => $basin->code,
        'geometry_type' => 'Polygon',
        'content_hash' => sha1($wkt),
        'geom' => $geom,
        'properties' => json_encode([
            'basin' => $row['name'],
            'annual_runoff' => $row['runoff'],
            'shapefile_area' => $row['area'],
            'perimeter' => $row['perimeter'],
            'datum' => 'Adindan UTM 37N → WGS84',
        ]),
        'min_lng' => $row['min_lng'],
        'min_lat' => $row['min_lat'],
        'max_lng' => $row['max_lng'],
        'max_lat' => $row['max_lat'],
        'basin_id' => $basin->id,
        'is_active' => true,
        'source_note' => 'EMA national basin boundary, 12 polygons.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $basinUpdates++;
}
echo "basins updated: {$basinUpdates}\n";

/* -------------------------------------------------------------- stations */

$byName = [];
foreach ($data['stations'] as $row) {
    $byName[mb_strtolower(preg_replace('/\s+/', ' ', trim($row['name'])))][] = $row;
}

$parameterIds = DB::table('parameters')->pluck('id', 'code');
$unitIds = DB::table('parameters')->pluck('default_unit_id', 'code');

$updated = 0;
$elevations = 0;
$assigned = 0;
$unmatched = [];

$availabilityMap = [
    'Rainfall' => 'rainfall',
    'Temprature' => 'temperature',
    'Relative_H' => 'relative_humidity',
    'Wind_speed' => 'wind_speed',
    'Sun_shine' => 'sunshine_duration',
];

foreach (MonitoringStation::cursor() as $station) {
    $candidates = $byName[mb_strtolower(preg_replace('/\s+/', ' ', trim($station->name)))] ?? [];
    if ($candidates === []) {
        $unmatched[] = $station->name;
        continue;
    }

    $match = null;
    $best = PHP_FLOAT_MAX;
    foreach ($candidates as $candidate) {
        $d = $distance((float) $station->latitude, (float) $station->longitude, $candidate['lat'], $candidate['lon']);
        if ($d < $best) {
            $best = $d;
            $match = $candidate;
        }
    }

    if ($match === null || $best > 5000) {
        $unmatched[] = $station->name;
        continue;
    }

    $elevation = is_numeric($match['altitude']) ? (float) $match['altitude'] : null;
    $basin = null;
    if (! empty($match['basin'])) {
        $basin = $resolveBasin($match['basin']);
    }

    $station->forceFill([
        'latitude' => $match['lat'],
        'longitude' => $match['lon'],
        'elevation_m' => $elevation ?? $station->elevation_m,
        'basin_id' => $basin?->id,
        'metadata' => [
            'source' => 'EMA Metrology_stations.shp',
            'datum' => 'Adindan UTM 37N → WGS84 (EPSG:1108)',
            'original_utm' => ['easting' => $match['easting'], 'northing' => $match['northing']],
            'class_code' => $match['class_code'],
            'status' => $match['status'],
            'availability' => array_filter($match['availability'], fn ($v) => $v !== ''),
        ],
    ])->save();

    $updated++;
    if ($elevation !== null) {
        $elevations++;
    }
    if ($basin) {
        $assigned++;
    }

    $desired = [];
    if ($station->station_class === 'precipitation') {
        $desired[] = 'rainfall';
    } else {
        $desired[] = 'rainfall';
        $desired[] = 'temperature';
    }
    foreach ($availabilityMap as $column => $code) {
        if (($match['availability'][$column] ?? '') !== '' && isset($parameterIds[$code])) {
            $desired[] = $code;
        }
    }
    $desired = array_values(array_unique(array_filter($desired, fn ($c) => isset($parameterIds[$c]))));

    DB::table('station_parameters')->where('monitoring_station_id', $station->id)
        ->whereNotIn('parameter_id', array_map(fn ($c) => $parameterIds[$c], $desired))
        ->delete();

    foreach ($desired as $code) {
        DB::table('station_parameters')->updateOrInsert(
            ['monitoring_station_id' => $station->id, 'parameter_id' => $parameterIds[$code]],
            [
                'measurement_unit_id' => $unitIds[$code] ?? null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}

echo "stations updated: {$updated}\n";
echo "with elevation: {$elevations}\n";
echo "basin assigned: {$assigned}\n";
echo 'unmatched: '.count($unmatched).' '.implode(', ', array_slice($unmatched, 0, 5))."\n";
echo 'station_parameters: '.DB::table('station_parameters')->count()."\n";
echo 'geo_features basin_boundary: '.DB::table('geo_features')->where('layer_type', 'basin_boundary')->count()."\n";
