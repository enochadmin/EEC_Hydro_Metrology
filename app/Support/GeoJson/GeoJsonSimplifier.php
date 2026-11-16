<?php

namespace App\Support\GeoJson;

/**
 * Server-side GeoJSON simplification.
 *
 * MariaDB 10.4 has no ST_Simplify, so the payload is reduced in PHP before it
 * is sent to the browser. Douglas–Peucker runs on degrees, which is accurate
 * enough at national scale and keeps the client's rendering work small.
 */
final class GeoJsonSimplifier
{
    /**
     * @param  array<string, mixed>  $geojson
     * @return array<string, mixed>
     */
    public static function simplify(array $geojson, float $tolerance): array
    {
        if ($tolerance <= 0) {
            return $geojson;
        }

        $type = $geojson['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $features = [];

            foreach ($geojson['features'] ?? [] as $feature) {
                if (is_array($feature)) {
                    $features[] = self::simplify($feature, $tolerance);
                }
            }

            return [...$geojson, 'features' => $features];
        }

        if ($type === 'Feature') {
            $geometry = $geojson['geometry'] ?? null;

            if (is_array($geometry)) {
                $geojson['geometry'] = self::simplifyGeometry($geometry, $tolerance);
            }

            return $geojson;
        }

        return self::simplifyGeometry($geojson, $tolerance);
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>
     */
    private static function simplifyGeometry(array $geometry, float $tolerance): array
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return $geometry;
        }

        $simplified = match ($type) {
            'LineString' => self::line($coordinates, $tolerance),
            'MultiLineString' => array_map(fn ($line) => is_array($line) ? self::line($line, $tolerance) : $line, $coordinates),
            'Polygon' => array_map(fn ($ring) => is_array($ring) ? self::ring($ring, $tolerance) : $ring, $coordinates),
            'MultiPolygon' => array_map(
                fn ($polygon) => is_array($polygon)
                    ? array_map(fn ($ring) => is_array($ring) ? self::ring($ring, $tolerance) : $ring, $polygon)
                    : $polygon,
                $coordinates,
            ),
            default => $coordinates,
        };

        return [...$geometry, 'coordinates' => $simplified];
    }

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $line
     * @return list<array{0: float|int, 1: float|int}>
     */
    private static function line(array $line, float $tolerance): array
    {
        if (count($line) < 3) {
            return $line;
        }

        return self::douglasPeucker($line, $tolerance);
    }

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $ring
     * @return list<array{0: float|int, 1: float|int}>
     */
    private static function ring(array $ring, float $tolerance): array
    {
        if (count($ring) < 4) {
            return $ring;
        }

        $closed = $ring[0] === $ring[count($ring) - 1];
        $open = $closed ? array_slice($ring, 0, -1) : $ring;

        if (count($open) < 3) {
            return $ring;
        }

        $simplified = self::douglasPeucker($open, $tolerance);

        if (count($simplified) < 3) {
            return $ring;
        }

        if ($closed) {
            $simplified[] = $simplified[0];
        }

        return $simplified;
    }

    /**
     * Iterative Douglas–Peucker to avoid a deep recursion on long rings.
     *
     * @param  list<array{0: float|int, 1: float|int}>  $points
     * @return list<array{0: float|int, 1: float|int}>
     */
    private static function douglasPeucker(array $points, float $tolerance): array
    {
        $count = count($points);

        if ($count < 3) {
            return $points;
        }

        $keep = array_fill(0, $count, false);
        $keep[0] = true;
        $keep[$count - 1] = true;

        $stack = [[0, $count - 1]];

        while ($stack !== []) {
            [$start, $end] = array_pop($stack);

            if ($end <= $start + 1) {
                continue;
            }

            $maxDistance = 0.0;
            $index = null;

            for ($i = $start + 1; $i < $end; $i++) {
                $distance = self::perpendicularDistance($points[$i], $points[$start], $points[$end]);

                if ($distance > $maxDistance) {
                    $maxDistance = $distance;
                    $index = $i;
                }
            }

            if ($index !== null && $maxDistance > $tolerance) {
                $keep[$index] = true;
                $stack[] = [$start, $index];
                $stack[] = [$index, $end];
            }
        }

        $result = [];

        foreach ($points as $index => $point) {
            if ($keep[$index]) {
                $result[] = $point;
            }
        }

        return $result;
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $point
     * @param  array{0: float|int, 1: float|int}  $start
     * @param  array{0: float|int, 1: float|int}  $end
     */
    private static function perpendicularDistance(array $point, array $start, array $end): float
    {
        $x = (float) $point[0];
        $y = (float) $point[1];
        $x1 = (float) $start[0];
        $y1 = (float) $start[1];
        $x2 = (float) $end[0];
        $y2 = (float) $end[1];

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt((($x - $x1) ** 2) + (($y - $y1) ** 2));
        }

        $t = ((($x - $x1) * $dx) + (($y - $y1) * $dy)) / (($dx ** 2) + ($dy ** 2));
        $t = max(0.0, min(1.0, $t));

        $projectedX = $x1 + ($t * $dx);
        $projectedY = $y1 + ($t * $dy);

        return sqrt((($x - $projectedX) ** 2) + (($y - $projectedY) ** 2));
    }
}
