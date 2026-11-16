<?php

namespace App\Services\Geography;

use App\Models\Basin;
use App\Models\BasinBoundary;
use App\Models\SubBasin;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\GeoJson\GeoJsonConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * GeoJSON boundary imports.
 *
 * A basin boundary is stored twice on purpose:
 *  - `basin_boundaries` keeps the authoritative, source-specific, versioned
 *    polygon (NOT NULL geometry so the SPATIAL INDEX is usable);
 *  - `basins.boundary` keeps a display copy of the current primary polygon so
 *    list and detail views never have to join the version table.
 */
class BoundaryService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function importForBasin(Basin $basin, array $attributes, UploadedFile $file, User $actor): BasinBoundary
    {
        $geometry = $this->parse($file);
        $isPrimary = (bool) ($attributes['is_primary'] ?? true);

        return DB::transaction(function () use ($basin, $attributes, $geometry, $isPrimary, $actor): BasinBoundary {
            if ($isPrimary) {
                BasinBoundary::query()
                    ->where('basin_id', $basin->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $boundary = BasinBoundary::createWithGeometry([
                'basin_id' => $basin->id,
                'name' => $attributes['name'],
                'source_id' => $attributes['source_id'] ?? $basin->data_source_id,
                'accuracy_m' => $attributes['accuracy_m'] ?? null,
                'is_primary' => $isPrimary,
                'effective_from' => $attributes['effective_from'] ?? null,
                'effective_to' => $attributes['effective_to'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ], $geometry['wkt']);

            if ($isPrimary) {
                $basin->setGeometryWkt('boundary', $geometry['wkt']);
            }

            $this->logger->log(
                'basin.boundary.imported',
                $basin,
                'Boundary "'.$boundary->name.'" imported for basin '.$basin->code.'.',
                [
                    'boundary_id' => $boundary->id,
                    'geometry_type' => $geometry['type'],
                    'positions' => $geometry['positions'],
                    'is_primary' => $isPrimary,
                    'srid' => 4326,
                ],
                $actor,
            );

            return $boundary;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{type: string, positions: int, bbox: array<string, float>}
     */
    public function importForSubBasin(SubBasin $subBasin, array $attributes, UploadedFile $file, User $actor): array
    {
        $geometry = $this->parse($file);

        DB::transaction(function () use ($subBasin, $attributes, $geometry, $actor): void {
            $subBasin->setGeometryWkt('boundary', $geometry['wkt']);

            $this->logger->log(
                'sub-basin.boundary.imported',
                $subBasin,
                'Boundary "'.($attributes['name'] ?? $subBasin->name).'" imported for sub-basin '.$subBasin->code.'.',
                [
                    'geometry_type' => $geometry['type'],
                    'positions' => $geometry['positions'],
                    'srid' => 4326,
                ],
                $actor,
            );
        });

        return [
            'type' => $geometry['type'],
            'positions' => $geometry['positions'],
            'bbox' => $geometry['bbox'],
        ];
    }

    /**
     * @return array{type: string, wkt: string, bbox: array<string, float>, positions: int}
     */
    private function parse(UploadedFile $file): array
    {
        try {
            return GeoJsonConverter::parse($file->getContent());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
    }

    public function deleteForBasin(Basin $basin, BasinBoundary $boundary, User $actor): void
    {
        DB::transaction(function () use ($basin, $boundary, $actor): void {
            $wasPrimary = $boundary->is_primary;

            $boundary->delete();

            if ($wasPrimary) {
                $basin->setGeometryWkt('boundary', null);
            }

            $this->logger->log(
                'basin.boundary.deleted',
                $basin,
                'Boundary "'.$boundary->name.'" removed from basin '.$basin->code.'.',
                ['boundary_id' => $boundary->id, 'was_primary' => $wasPrimary],
                $actor,
            );
        });
    }

    public function clearForSubBasin(SubBasin $subBasin, User $actor): void
    {
        DB::transaction(function () use ($subBasin, $actor): void {
            $subBasin->setGeometryWkt('boundary', null);

            $this->logger->log(
                'sub-basin.boundary.deleted',
                $subBasin,
                'Boundary removed from sub-basin '.$subBasin->code.'.',
                [],
                $actor,
            );
        });
    }
}
