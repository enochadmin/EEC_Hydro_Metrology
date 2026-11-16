<?php

namespace App\Support\GeoJson;

use InvalidArgumentException;
use JsonException;

/**
 * Reads a GeoJSON file containing one or more features and returns WKT.
 *
 * Unlike GeoJsonConverter (which is restricted to a single boundary polygon),
 * this reader supports the geometry families used by map layers: points,
 * lines and polygons, including their multi- variants.
 *
 * Validation performed here:
 *  - the document is valid JSON and a Feature / FeatureCollection / geometry;
 *  - the declared CRS is WGS84 / EPSG:4326 / CRS84 (projected files rejected);
 *  - every coordinate is numeric and inside the WGS84 range;
 *  - polygon rings are closed, have at least four positions and a non-zero area;
 *  - feature and position budgets are respected.
 *
 * MariaDB 10.4 has no ST_IsValid, so full topological self-intersection
 * checking is not available; the ring checks above catch the common failures
 * before the geometry reaches MySQL.
 */
final class GeoJsonFeatureReader
{
    public const MAX_FEATURES = 5000;

    public const MAX_POSITIONS = 20000;

    /**
     * @param  list<string>  $allowedGeometryTypes
     * @return array{
     *     features: list<array{
     *         geometry_type: string,
     *         wkt: string,
     *         bbox: array{min_lng: float, min_lat: float, max_lng: float, max_lat: float},
     *         positions: int,
     *         properties: array<string, mixed>,
     *         name: string|null
     *     }>,
     *     bbox: array{min_lng: float, min_lat: float, max_lng: float, max_lat: float},
     *     positions: int
     * }
     *
     * @throws InvalidArgumentException
     */
    public static function read(string $json, array $allowedGeometryTypes): array
    {
        try {
            $decoded = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The uploaded file is not valid JSON.');
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The GeoJSON document must be a JSON object.');
        }

        self::assertCoordinateSystem($decoded['crs'] ?? null);

        $rawFeatures = self::extractFeatures($decoded);

        if ($rawFeatures === []) {
            throw new InvalidArgumentException('The file does not contain any GeoJSON features.');
        }

        if (count($rawFeatures) > self::MAX_FEATURES) {
            throw new InvalidArgumentException(
                'The file contains '.count($rawFeatures).' features; the limit is '.self::MAX_FEATURES.'. Split the dataset before importing.'
            );
        }

        $features = [];
        $bbox = ['min_lng' => INF, 'min_lat' => INF, 'max_lng' => -INF, 'max_lat' => -INF];
        $totalPositions = 0;

        foreach ($rawFeatures as $index => $feature) {
            if (! is_array($feature)) {
                throw new InvalidArgumentException('Feature '.($index + 1).' is not a JSON object.');
            }

            $geometry = $feature['geometry'] ?? null;

            if (! is_array($geometry)) {
                throw new InvalidArgumentException('Feature '.($index + 1).' does not contain a geometry object.');
            }

            $type = $geometry['type'] ?? null;

            if (! is_string($type) || ! in_array($type, $allowedGeometryTypes, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Feature %d uses %s; this layer accepts %s.',
                    $index + 1,
                    is_string($type) ? $type : 'an unknown geometry',
                    implode(' or ', $allowedGeometryTypes),
                ));
            }

            $positions = 0;
            $wkt = self::geometryToWkt($type, $geometry['coordinates'] ?? null, $bbox, $positions);
            $totalPositions += $positions;

            if ($totalPositions > self::MAX_POSITIONS) {
                throw new InvalidArgumentException(
                    'The file contains more than '.self::MAX_POSITIONS.' positions. Simplify the geometry before importing.'
                );
            }

            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $featureBbox = GeoJsonConverter::bboxFromWkt($wkt) ?? $bbox;

            $features[] = [
                'geometry_type' => $type,
                'wkt' => $wkt,
                'bbox' => $featureBbox,
                'positions' => $positions,
                'properties' => $properties,
                'name' => self::nameFrom($properties),
            ];
        }

        return [
            'features' => $features,
            'bbox' => $bbox,
            'positions' => $totalPositions,
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<mixed>
     */
    private static function extractFeatures(array $document): array
    {
        $type = $document['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $features = $document['features'] ?? null;

            return is_array($features) ? array_values($features) : [];
        }

        if ($type === 'Feature') {
            return [$document];
        }

        if ($type === 'GeometryCollection') {
            throw new InvalidArgumentException('GeometryCollection is not supported; import a FeatureCollection instead.');
        }

        if (isset($document['coordinates'])) {
            return [[
                'type' => 'Feature',
                'geometry' => $document,
                'properties' => [],
            ]];
        }

        return [];
    }

    private static function assertCoordinateSystem(mixed $crs): void
    {
        if (! is_array($crs)) {
            return;
        }

        $name = $crs['properties']['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            return;
        }

        $normalized = strtoupper($name);

        if (str_contains($normalized, '4326') || str_contains($normalized, 'CRS84') || str_contains($normalized, 'WGS84')) {
            return;
        }

        throw new InvalidArgumentException(
            'The file declares '.$name.'. Only WGS84 (EPSG:4326) coordinates can be imported; reproject the dataset before uploading.'
        );
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function geometryToWkt(string $type, mixed $coordinates, array &$bbox, int &$positions): string
    {
        return match ($type) {
            'Point' => 'POINT('.self::formatPosition(self::parsePosition($coordinates, $bbox, $positions)).')',
            'MultiPoint' => 'MULTIPOINT('.self::multiPointBody($coordinates, $bbox, $positions).')',
            'LineString' => 'LINESTRING('.self::lineBody($coordinates, $bbox, $positions).')',
            'MultiLineString' => 'MULTILINESTRING('.self::multiLineBody($coordinates, $bbox, $positions).')',
            'Polygon' => 'POLYGON('.self::polygonBody($coordinates, $bbox, $positions).')',
            'MultiPolygon' => 'MULTIPOLYGON('.self::multiPolygonBody($coordinates, $bbox, $positions).')',
            default => throw new InvalidArgumentException('The geometry type '.$type.' is not supported.'),
        };
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function multiPointBody(mixed $coordinates, array &$bbox, int &$positions): string
    {
        if (! is_array($coordinates) || $coordinates === []) {
            throw new InvalidArgumentException('A MultiPoint must contain at least one position.');
        }

        $parts = [];

        foreach ($coordinates as $position) {
            $parts[] = '('.self::formatPosition(self::parsePosition($position, $bbox, $positions)).')';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function multiLineBody(mixed $lines, array &$bbox, int &$positions): string
    {
        if (! is_array($lines) || $lines === []) {
            throw new InvalidArgumentException('A MultiLineString must contain at least one line.');
        }

        $parts = [];

        foreach ($lines as $line) {
            $parts[] = '('.self::lineBody($line, $bbox, $positions).')';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function lineBody(mixed $line, array &$bbox, int &$positions): string
    {
        if (! is_array($line) || count($line) < 2) {
            throw new InvalidArgumentException('A line must contain at least two positions.');
        }

        $points = [];

        foreach ($line as $position) {
            $points[] = self::formatPosition(self::parsePosition($position, $bbox, $positions));
        }

        return implode(', ', $points);
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function multiPolygonBody(mixed $polygons, array &$bbox, int &$positions): string
    {
        if (! is_array($polygons) || $polygons === []) {
            throw new InvalidArgumentException('A MultiPolygon must contain at least one polygon.');
        }

        $parts = [];

        foreach ($polygons as $polygon) {
            $parts[] = '('.self::polygonBody($polygon, $bbox, $positions).')';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function polygonBody(mixed $rings, array &$bbox, int &$positions): string
    {
        if (! is_array($rings) || $rings === []) {
            throw new InvalidArgumentException('A polygon must contain at least one linear ring.');
        }

        $parts = [];

        foreach ($rings as $index => $ring) {
            $parts[] = '('.self::ringBody($ring, (int) $index, $bbox, $positions).')';
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function ringBody(mixed $ring, int $ringIndex, array &$bbox, int &$positions): string
    {
        if (! is_array($ring)) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' is not a list of positions.');
        }

        if (count($ring) < 4) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' must contain at least four positions.');
        }

        $points = [];

        foreach ($ring as $position) {
            $points[] = self::parsePosition($position, $bbox, $positions);
        }

        $lastIndex = count($points) - 1;

        if ($points[0] !== $points[$lastIndex]) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' is not closed: the first and last positions must be identical.');
        }

        if (abs(self::ringArea($points)) < 1e-12) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' encloses no area; check the coordinate order.');
        }

        return implode(', ', array_map([self::class, 'formatPosition'], $points));
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     * @return array{0: float, 1: float}
     */
    private static function parsePosition(mixed $position, array &$bbox, int &$positions): array
    {
        if (! is_array($position) || count($position) < 2) {
            throw new InvalidArgumentException('Every position must be a [longitude, latitude] pair.');
        }

        $lng = $position[0];
        $lat = $position[1];

        if (! is_numeric($lng) || ! is_numeric($lat)) {
            throw new InvalidArgumentException('Every coordinate must be numeric.');
        }

        $lng = (float) $lng;
        $lat = (float) $lat;

        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException(
                'Longitude '.$lng.' is outside the WGS84 range. The file may be in a projected coordinate system; reproject it to EPSG:4326.'
            );
        }

        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException(
                'Latitude '.$lat.' is outside the WGS84 range. The file may be in a projected coordinate system; reproject it to EPSG:4326.'
            );
        }

        $positions++;
        $bbox['min_lng'] = min($bbox['min_lng'], $lng);
        $bbox['min_lat'] = min($bbox['min_lat'], $lat);
        $bbox['max_lng'] = max($bbox['max_lng'], $lng);
        $bbox['max_lat'] = max($bbox['max_lat'], $lat);

        return [$lng, $lat];
    }

    /**
     * @param  array{0: float, 1: float}  $position
     */
    private static function formatPosition(array $position): string
    {
        return self::number($position[0]).' '.self::number($position[1]);
    }

    private static function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function ringArea(array $points): float
    {
        $sum = 0.0;
        $count = count($points);

        for ($i = 0; $i < $count - 1; $i++) {
            $sum += ($points[$i][0] * $points[$i + 1][1]) - ($points[$i + 1][0] * $points[$i][1]);
        }

        return $sum / 2;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function nameFrom(array $properties): ?string
    {
        foreach (['name', 'NAME', 'Name', 'title', 'TITLE', 'feature_name', 'shapeName', 'NAME_1'] as $key) {
            $value = $properties[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 180);
            }
        }

        return null;
    }
}
