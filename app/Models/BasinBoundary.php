<?php

namespace App\Models;

use App\Models\Concerns\HasGeometryColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * One authoritative polygon for a basin from a specific source and version.
 */
class BasinBoundary extends Model
{
    use HasGeometryColumn, SoftDeletes;

    /** @var list<string> */
    protected array $geometryColumns = ['geom'];

    protected $fillable = [
        'basin_id',
        'name',
        'source_id',
        'srid',
        'accuracy_m',
        'is_primary',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'srid' => 'integer',
            'accuracy_m' => 'decimal:2',
            'is_primary' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * Create a boundary together with its polygon.
     *
     * `geom` is NOT NULL so that a SPATIAL INDEX can exist, which means a
     * boundary cannot be inserted first and patched afterwards. GIS imports and
     * seeders must use this method (or an equivalent bulk insert) so the
     * geometry is written in the same statement with SRID 4326.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithGeometry(array $attributes, string $wkt, int $srid = 4326): self
    {
        $id = DB::table((new static)->getTable())->insertGetId([
            ...$attributes,
            'srid' => $srid,
            'geom' => DB::raw('ST_GeomFromText('.DB::getPdo()->quote($wkt).', '.(int) $srid.')'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return static::query()->findOrFail($id);
    }

    public function basin(): BelongsTo
    {
        return $this->belongsTo(Basin::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'source_id');
    }
}
