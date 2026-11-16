<?php

namespace App\Services\Gis;

use App\Models\GeoFeature;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\GeoJson\GeoJsonFeatureReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Imports the supplementary map layers: national boundary, rivers and lakes.
 *
 * The reader performs geometry and CRS validation; this service adds the
 * per-layer geometry contract, duplicate protection, parent-basin association
 * and the audit entry. Everything runs in one transaction, so a bad feature in
 * a multi-feature file leaves no partial layer behind.
 */
class GeoFeatureImportService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{layer_type: string, features: int, positions: int}
     */
    public function import(array $attributes, UploadedFile $file, User $actor): array
    {
        $layer = (string) $attributes['layer_type'];

        $allowedGeometry = match ($layer) {
            GeoFeature::LAYER_NATIONAL_BOUNDARY => ['Polygon', 'MultiPolygon'],
            GeoFeature::LAYER_RIVER => ['LineString', 'MultiLineString'],
            GeoFeature::LAYER_LAKE => ['Polygon', 'MultiPolygon'],
            default => throw ValidationException::withMessages([
                'layer_type' => 'Choose one of the supported layers: national boundary, river or lake.',
            ]),
        };

        try {
            $read = GeoJsonFeatureReader::read($file->getContent(), $allowedGeometry);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($layer === GeoFeature::LAYER_NATIONAL_BOUNDARY && count($read['features']) !== 1) {
            throw ValidationException::withMessages([
                'file' => 'A national boundary import must contain exactly one polygon feature.',
            ]);
        }

        return DB::transaction(function () use ($layer, $read, $attributes, $actor): array {
            if ($layer === GeoFeature::LAYER_NATIONAL_BOUNDARY) {
                GeoFeature::query()
                    ->where('layer_type', $layer)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $created = 0;
            $total = count($read['features']);

            foreach ($read['features'] as $index => $feature) {
                $hash = hash('sha256', $layer.'|'.$feature['wkt']);

                if (GeoFeature::withTrashed()->where('content_hash', $hash)->exists()) {
                    throw ValidationException::withMessages([
                        'file' => 'Feature '.($index + 1).' duplicates a geometry that is already stored in this layer.',
                    ]);
                }

                $name = $this->resolveName(
                    $layer,
                    isset($attributes['name']) ? (string) $attributes['name'] : null,
                    $feature['name'],
                    $index,
                    $total,
                );

                DB::table('geo_features')->insert([
                    'layer_type' => $layer,
                    'name' => $name,
                    'code' => isset($attributes['code']) && $attributes['code'] !== ''
                        ? Str::limit((string) $attributes['code'], 80, '')
                        : null,
                    'geometry_type' => $feature['geometry_type'],
                    'geom' => DB::raw("ST_GeomFromText('".$feature['wkt']."', 4326)"),
                    'properties' => json_encode(
                        $feature['properties'] + ['imported_at' => now()->toIso8601String()],
                        JSON_UNESCAPED_UNICODE,
                    ) ?: null,
                    'min_lng' => $feature['bbox']['min_lng'],
                    'min_lat' => $feature['bbox']['min_lat'],
                    'max_lng' => $feature['bbox']['max_lng'],
                    'max_lat' => $feature['bbox']['max_lat'],
                    'content_hash' => $hash,
                    'basin_id' => $attributes['basin_id'] ?? null,
                    'created_by' => $actor->getKey(),
                    'source_note' => $attributes['source_note'] ?? null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            }

            $this->logger->log(
                'gis.features.imported',
                null,
                $created.' '.Str::plural('feature', $created).' imported into the '.str_replace('_', ' ', $layer).' layer.',
                [
                    'layer_type' => $layer,
                    'features' => $created,
                    'positions' => $read['positions'],
                    'srid' => 4326,
                ],
                $actor,
            );

            return ['layer_type' => $layer, 'features' => $created, 'positions' => $read['positions']];
        });
    }

    public function delete(GeoFeature $feature, User $actor): void
    {
        DB::transaction(function () use ($feature, $actor): void {
            $name = $feature->name;
            $layer = $feature->layer_type;
            $id = $feature->id;

            $feature->delete();

            $this->logger->log(
                'gis.feature.deleted',
                null,
                'Feature "'.$name.'" removed from the '.str_replace('_', ' ', $layer).' layer.',
                ['layer_type' => $layer, 'feature_id' => $id, 'name' => $name],
                $actor,
            );
        });
    }

    private function resolveName(string $layer, ?string $provided, ?string $fromFile, int $index, int $total): string
    {
        $fallback = Str::headline($layer);
        $base = trim((string) ($provided ?: $fromFile ?: $fallback));

        if ($base === '') {
            $base = $fallback;
        }

        if ($total > 1) {
            $base .= ' '.($index + 1);
        }

        return Str::limit($base, 180, '');
    }
}
