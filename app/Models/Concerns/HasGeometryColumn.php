<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read and write WGS84 (EPSG:4326) geometry columns.
 *
 * MariaDB 10.x has no SRID column attribute, so the SRID is supplied on every
 * write through ST_GeomFromText(..., 4326) and can be verified with ST_SRID().
 * Eloquent cannot bind a geometry value as a plain string, hence the explicit
 * query-builder calls.
 */
trait HasGeometryColumn
{
    /**
     * Return the stored geometry as WKT, or null when the column is empty.
     */
    public function geometryWkt(string $column = 'boundary'): ?string
    {
        $this->assertGeometryColumn($column);

        if (! $this->exists) {
            return null;
        }

        $row = DB::selectOne(
            sprintf('select ST_AsText(%s) as wkt from %s where id = ?', $this->wrap($column), $this->getTable()),
            [$this->getKey()],
        );

        return $row?->wkt;
    }

    /**
     * Store a WKT geometry with SRID 4326, or clear the column with null.
     */
    public function setGeometryWkt(string $column, ?string $wkt): static
    {
        $this->assertGeometryColumn($column);

        if (! $this->exists) {
            throw new InvalidArgumentException('The model must be persisted before writing geometry.');
        }

        if ($wkt === null) {
            DB::update(
                sprintf('update %s set %s = null where id = ?', $this->getTable(), $this->wrap($column)),
                [$this->getKey()],
            );

            return $this;
        }

        DB::update(
            sprintf('update %s set %s = ST_GeomFromText(?, 4326) where id = ?', $this->getTable(), $this->wrap($column)),
            [$wkt, $this->getKey()],
        );

        return $this;
    }

    private function assertGeometryColumn(string $column): void
    {
        if (! in_array($column, $this->geometryColumns(), true)) {
            throw new InvalidArgumentException("Unknown geometry column [{$column}] on ".static::class.'.');
        }
    }

    /**
     * @return list<string>
     */
    private function geometryColumns(): array
    {
        return property_exists($this, 'geometryColumns') ? $this->geometryColumns : ['boundary'];
    }

    private function wrap(string $column): string
    {
        return '`'.str_replace('`', '', $column).'`';
    }
}
