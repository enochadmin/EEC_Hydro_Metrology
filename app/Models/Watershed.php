<?php

namespace App\Models;

use App\Models\Concerns\HasGeometryColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Watershed extends Model
{
    use HasGeometryColumn, SoftDeletes;

    /** @var list<string> */
    protected array $geometryColumns = ['boundary'];

    protected $fillable = [
        'sub_basin_id',
        'parent_id',
        'code',
        'name',
        'name_alt',
        'description',
        'level',
        'area_km2',
        'centroid_lat',
        'centroid_lng',
        'data_source_id',
        'data_status',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'area_km2' => 'decimal:2',
            'centroid_lat' => 'decimal:7',
            'centroid_lng' => 'decimal:7',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subBasin(): BelongsTo
    {
        return $this->belongsTo(SubBasin::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(MonitoringStation::class);
    }

    /**
     * @param  Builder<Watershed>  $query
     * @return Builder<Watershed>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
