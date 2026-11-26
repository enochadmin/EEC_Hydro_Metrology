<?php

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * Converts between WGS84 geographic coordinates and the Universal Transverse
 * Mercator grid used by Ethiopian station lists.
 *
 * The station spreadsheets in circulation label their columns "Lat" and "Lon"
 * but carry projected metres (zone 37N, central meridian 39°E). Storing those
 * numbers as degrees would put every station off the coast of Somalia, so the
 * importer converts them before they reach `monitoring_stations`.
 *
 * Series expansion is the standard Transverse Mercator solution with the
 * WGS84 ellipsoid and k0 = 0.9996, accurate to well under a metre inside the
 * zone. It is implemented here instead of pulling in proj/GDAL because the
 * application only needs this one projection and must not depend on a native
 * library being present on the server.
 */
class CoordinateTransformer
{
    private const SEMI_MAJOR_AXIS = 6378137.0;

    private const FLATTENING = 1 / 298.257223563;

    private const SCALE_FACTOR = 0.9996;

    private const FALSE_EASTING = 500000.0;

    public const ETHIOPIA_BOUNDS = ['min_lat' => 3.0, 'max_lat' => 15.5, 'min_lng' => 32.5, 'max_lng' => 48.5];

    public function centralMeridian(int $zone): float
    {
        return ($zone - 1) * 6 - 180 + 3;
    }

    /**
     * A pair of values is projected when either one exceeds the degree range.
     */
    public function looksProjected(mixed $first, mixed $second): bool
    {
        foreach ([$first, $second] as $value) {
            if (is_numeric($value) && abs((float) $value) > 180) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide which value is the easting and which is the northing.
     *
     * Files that carry projected metres frequently keep the original "Lat"/"Lon"
     * headers, with the easting in the first column. Both orders are converted
     * and the one that lands inside Ethiopia wins; ties keep the file's order.
     *
     * @return array{0: float, 1: float} easting, northing
     */
    public function resolveAxes(float $first, float $second, int $zone): array
    {
        $candidates = [[$first, $second], [$second, $first]];

        foreach ($candidates as [$easting, $northing]) {
            $point = $this->toGeographic($easting, $northing, $zone);

            if ($point !== null && $this->insideEthiopia($point['latitude'], $point['longitude'])) {
                return [$easting, $northing];
            }
        }

        return [$first, $second];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function toGeographic(float $easting, float $northing, int $zone, bool $southernHemisphere = false): ?array
    {
        if ($zone < 1 || $zone > 60) {
            throw new InvalidArgumentException('UTM zone must be between 1 and 60.');
        }

        if ($easting < -200000 || $easting > 1400000 || abs($northing) > 10000000) {
            return null;
        }

        $a = self::SEMI_MAJOR_AXIS;
        $f = self::FLATTENING;
        $e2 = $f * (2 - $f);
        $ep2 = $e2 / (1 - $e2);

        $x = $easting - self::FALSE_EASTING;
        $y = $northing - ($southernHemisphere ? 10000000.0 : 0.0);

        $m = $y / self::SCALE_FACTOR;
        $mu = $m / ($a * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));

        $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));

        $phi1 = $mu
            + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
            + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
            + (151 * $e1 ** 3 / 96) * sin(6 * $mu)
            + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);

        $sinPhi1 = sin($phi1);
        $cosPhi1 = cos($phi1);
        $tanPhi1 = tan($phi1);

        $c1 = $ep2 * $cosPhi1 ** 2;
        $t1 = $tanPhi1 ** 2;
        $n1 = $a / sqrt(1 - $e2 * $sinPhi1 ** 2);
        $r1 = $a * (1 - $e2) / (1 - $e2 * $sinPhi1 ** 2) ** 1.5;
        $d = $x / ($n1 * self::SCALE_FACTOR);

        $latitude = $phi1 - ($n1 * $tanPhi1 / $r1) * (
            $d ** 2 / 2
            - (5 + 3 * $t1 + 10 * $c1 - 4 * $c1 ** 2 - 9 * $ep2) * $d ** 4 / 24
            + (61 + 90 * $t1 + 298 * $c1 + 45 * $t1 ** 2 - 252 * $ep2 - 3 * $c1 ** 2) * $d ** 6 / 720
        );

        $longitude = deg2rad($this->centralMeridian($zone)) + (
            $d
            - (1 + 2 * $t1 + $c1) * $d ** 3 / 6
            + (5 - 2 * $c1 + 28 * $t1 - 3 * $c1 ** 2 + 8 * $ep2 + 24 * $t1 ** 2) * $d ** 5 / 120
        ) / $cosPhi1;

        $latitude = rad2deg($latitude);
        $longitude = rad2deg($longitude);

        if (! is_finite($latitude) || ! is_finite($longitude)) {
            return null;
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return ['latitude' => round($latitude, 7), 'longitude' => round($longitude, 7)];
    }

    /**
     * @return array{easting: float, northing: float}
     */
    public function fromGeographic(float $latitude, float $longitude, int $zone): array
    {
        if ($zone < 1 || $zone > 60) {
            throw new InvalidArgumentException('UTM zone must be between 1 and 60.');
        }

        $a = self::SEMI_MAJOR_AXIS;
        $f = self::FLATTENING;
        $e2 = $f * (2 - $f);
        $ep2 = $e2 / (1 - $e2);

        $phi = deg2rad($latitude);
        $lambda = deg2rad($longitude);
        $lambda0 = deg2rad($this->centralMeridian($zone));

        $n = $a / sqrt(1 - $e2 * sin($phi) ** 2);
        $t = tan($phi) ** 2;
        $c = $ep2 * cos($phi) ** 2;
        $aTerm = cos($phi) * ($lambda - $lambda0);

        $m = $a * (
            (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256) * $phi
            - (3 * $e2 / 8 + 3 * $e2 ** 2 / 32 + 45 * $e2 ** 3 / 1024) * sin(2 * $phi)
            + (15 * $e2 ** 2 / 256 + 45 * $e2 ** 3 / 1024) * sin(4 * $phi)
            - (35 * $e2 ** 3 / 3072) * sin(6 * $phi)
        );

        $easting = self::FALSE_EASTING + self::SCALE_FACTOR * $n * (
            $aTerm
            + (1 - $t + $c) * $aTerm ** 3 / 6
            + (5 - 18 * $t + $t ** 2 + 72 * $c - 58 * $ep2) * $aTerm ** 5 / 120
        );

        $northing = self::SCALE_FACTOR * (
            $m + $n * tan($phi) * (
                $aTerm ** 2 / 2
                + (5 - $t + 9 * $c + 4 * $c ** 2) * $aTerm ** 4 / 24
                + (61 - 58 * $t + $t ** 2 + 600 * $c - 330 * $ep2) * $aTerm ** 6 / 720
            )
        );

        if ($latitude < 0) {
            $northing += 10000000.0;
        }

        return ['easting' => round($easting, 4), 'northing' => round($northing, 4)];
    }

    public function insideEthiopia(float $latitude, float $longitude): bool
    {
        $bounds = self::ETHIOPIA_BOUNDS;

        return $latitude >= $bounds['min_lat'] && $latitude <= $bounds['max_lat']
            && $longitude >= $bounds['min_lng'] && $longitude <= $bounds['max_lng'];
    }

    /**
     * Great-circle distance in metres, used for duplicate detection.
     */
    public function distanceMetres(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $radius = 6371000.0;
        $latDelta = deg2rad($latB - $latA);
        $lngDelta = deg2rad($lngB - $lngA);

        $h = sin($latDelta / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($lngDelta / 2) ** 2;

        return 2 * $radius * asin(min(1.0, sqrt($h)));
    }
}
