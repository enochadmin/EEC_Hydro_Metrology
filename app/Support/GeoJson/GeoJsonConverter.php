<?php

namespace App\Support\GeoJson;

use InvalidArgumentException;
use JsonException;

/**
 * Dependency-free GeoJSON → WKT conversion for boundary imports.
 *
 * Only the geometries a basin boundary can legitimately be are accepted
 * (Polygon, MultiPolygon, or a single-feature Feature / FeatureCollection).
 * Every position is range-checked before it reaches MySQL, and ambiguous
 * documents are rejected with a message the uploader can act on rather than
 * being silently repaired.
 */
final class GeoJsonConverter
{
    public const MAX_POSITIONS = 20000;

    public const MAX_RINGS = 500;

    /**
     * @return array{
     *     type: string,
     *     wkt: string,
     *     bbox: array{min_lng: float, min_lat: float, max_lng: float, max_lat: float},
     *     positions: int
     * }
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The uploaded file is not valid JSON.');
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The GeoJSON document must be a JSON object.');
        }

        $geometry = self::extractGeometry($decoded);
        $type = $geometry['type'] ?? null;

        $bbox = ['min_lng' => INF, 'min_lat' => INF, 'max_lng' => -INF, 'max_lat' => -INF];
        $positions = 0;

        $wkt = match ($type) {
            'Polygon' => self::polygonToWkt($geometry['coordinates'] ?? null, $bbox, $positions),
            'MultiPolygon' => self::multiPolygonToWkt($geometry['coordinates'] ?? null, $bbox, $positions),
            default => throw new InvalidArgumentException(
                'Only Polygon or MultiPolygon geometries can be imported as a boundary. Received: '.($type ?: 'no geometry type').'.'
            ),
        };

        if ($positions > self::MAX_POSITIONS) {
            throw new InvalidArgumentException(
                'The boundary contains '.$positions.' positions; the limit is '.self::MAX_POSITIONS.'. Simplify the polygon before importing.'
            );
        }

        return [
            'type' => $type,
            'wkt' => $wkt,
            'bbox' => $bbox,
            'positions' => $positions,
        ];
    }

    /**
     * Bounding box derived from an envelope WKT (or any WKT with coordinate pairs).
     *
     * @return array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}|null
     */
    public static function bboxFromWkt(?string $wkt): ?array
    {
        if ($wkt === null || trim($wkt) === '') {
            return null;
        }

        if (preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/', $wkt, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        $bbox = ['min_lng' => INF, 'min_lat' => INF, 'max_lng' => -INF, 'max_lat' => -INF];

        foreach ($matches as $match) {
            $lng = (float) $match[1];
            $lat = (float) $match[2];

            $bbox['min_lng'] = min($bbox['min_lng'], $lng);
            $bbox['min_lat'] = min($bbox['min_lat'], $lat);
            $bbox['max_lng'] = max($bbox['max_lng'], $lng);
            $bbox['max_lat'] = max($bbox['max_lat'], $lat);
        }

        return $bbox;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private static function extractGeometry(array $document): array
    {
        $type = $document['type'] ?? null;

        if ($type === 'Feature') {
            return self::geometryFromFeature($document);
        }

        if ($type === 'FeatureCollection') {
            $features = $document['features'] ?? null;

            if (! is_array($features)) {
                throw new InvalidArgumentException('The feature collection does not contain a "features" array.');
            }

            if (count($features) !== 1) {
                throw new InvalidArgumentException(
                    'A boundary import must contain exactly one feature; this file contains '.count($features).'. Split the collection and import one boundary at a time.'
                );
            }

            $feature = $features[0] ?? null;

            if (! is_array($feature)) {
                throw new InvalidArgumentException('The feature collection contains an invalid feature.');
            }

            return self::geometryFromFeature($feature);
        }

        if ($type === 'GeometryCollection') {
            throw new InvalidArgumentException('GeometryCollection is not supported; import a single polygon instead.');
        }

        if (! isset($document['coordinates'])) {
            throw new InvalidArgumentException('The file is not a GeoJSON geometry: a "type" and "coordinates" member are required.');
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>
     */
    private static function geometryFromFeature(array $feature): array
    {
        $geometry = $feature['geometry'] ?? null;

        if (! is_array($geometry)) {
            throw new InvalidArgumentException('The GeoJSON feature does not contain a geometry object.');
        }

        return $geometry;
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function polygonToWkt(mixed $rings, array &$bbox, int &$positions): string
    {
        if (! is_array($rings) || $rings === []) {
            throw new InvalidArgumentException('A polygon must contain at least one linear ring.');
        }

        if (count($rings) > self::MAX_RINGS) {
            throw new InvalidArgumentException('The polygon contains more than '.self::MAX_RINGS.' rings.');
        }

        $parts = [];

        foreach ($rings as $index => $ring) {
            $parts[] = '('.self::ringToWkt($ring, (int) $index, $bbox, $positions).')';
        }

        return 'POLYGON('.implode(', ', $parts).')';
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function multiPolygonToWkt(mixed $polygons, array &$bbox, int &$positions): string
    {
        if (! is_array($polygons) || $polygons === []) {
            throw new InvalidArgumentException('A multi-polygon must contain at least one polygon.');
        }

        $parts = [];

        foreach ($polygons as $index => $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                throw new InvalidArgumentException('Polygon '.((int) $index + 1).' in the multi-polygon has no rings.');
            }

            $rings = [];

            foreach ($polygon as $ring) {
                $rings[] = '('.self::ringToWkt($ring, (int) $index, $bbox, $positions).')';
            }

            $parts[] = '('.implode(', ', $rings).')';
        }

        return 'MULTIPOLYGON('.implode(', ', $parts).')';
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function ringToWkt(mixed $ring, int $ringIndex, array &$bbox, int &$positions): string
    {
        if (! is_array($ring)) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' is not a list of positions.');
        }

        if (count($ring) < 4) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' must contain at least four positions to form a closed area.');
        }

        $points = [];

        foreach ($ring as $position) {
            $points[] = self::positionToWkt($position, $bbox, $positions);
        }

        if ($points[0] !== $points[count($points) - 1]) {
            throw new InvalidArgumentException('Ring '.($ringIndex + 1).' is not closed: the first and last positions must be identical.');
        }

        return implode(', ', $points);
    }

    /**
     * @param  array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}  $bbox
     */
    private static function positionToWkt(mixed $position, array &$bbox, int &$positions): string
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
            throw new InvalidArgumentException('Longitude '.$lng.' is outside the valid range of -180 to 180 degrees.');
        }

        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException('Latitude '.$lat.' is outside the valid range of -90 to 90 degrees.');
        }

        $positions++;
        $bbox['min_lng'] = min($bbox['min_lng'], $lng);
        $bbox['min_lat'] = min($bbox['min_lat'], $lat);
        $bbox['max_lng'] = max($bbox['max_lng'], $lng);
        $bbox['max_lat'] = max($bbox['max_lat'], $lat);

        return self::number($lng).' '.self::number($lat);
    }

    private static function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
