<?php

namespace App\Models;

use App\Models\Concerns\HasGeometryColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubBasin extends Model
{
    use HasFactory, HasGeometryColumn, SoftDeletes;

    /** @var list<string> */
    protected array $geometryColumns = ['boundary'];

    protected $fillable = [
        'basin_id',
        'code',
        'name',
        'name_alt',
        'description',
        'area_km2',
        'centroid_lat',
        'centroid_lng',
        'elevation_m',
        'data_source_id',
        'data_status',
        'source_note',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'area_km2' => 'decimal:2',
            'centroid_lat' => 'decimal:7',
            'centroid_lng' => 'decimal:7',
            'elevation_m' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function basin(): BelongsTo
    {
        return $this->belongsTo(Basin::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function watersheds(): HasMany
    {
        return $this->hasMany(Watershed::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(MonitoringStation::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /**
     * @param  Builder<SubBasin>  $query
     * @return Builder<SubBasin>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
