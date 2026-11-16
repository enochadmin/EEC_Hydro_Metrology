<?php

namespace App\Models;

use App\Models\Concerns\HasGeometryColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Basin extends Model
{
    use HasFactory, HasGeometryColumn, SoftDeletes;

    /**
     * Columns accepted by the geometry helpers.
     *
     * `boundary` is an optional simplified display geometry; authoritative
     * polygons are stored per source in `basin_boundaries`.
     *
     * @var list<string>
     */
    protected array $geometryColumns = ['boundary'];

    protected $fillable = [
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

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function subBasins(): HasMany
    {
        return $this->hasMany(SubBasin::class);
    }

    public function watersheds(): HasManyThrough
    {
        return $this->hasManyThrough(Watershed::class, SubBasin::class);
    }

    public function boundaries(): HasMany
    {
        return $this->hasMany(BasinBoundary::class);
    }

    public function primaryBoundary(): HasOne
    {
        return $this->hasOne(BasinBoundary::class)->where('is_primary', true);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(MonitoringStation::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BasinUserAssignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'basin_user_assignments')
            ->withPivot(['access_level', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
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
     * @param  Builder<Basin>  $query
     * @return Builder<Basin>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
