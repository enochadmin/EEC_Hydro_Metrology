<?php

namespace App\Models;

use App\Models\Concerns\HasGeometryColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonitoringStation extends Model
{
    use HasGeometryColumn, SoftDeletes;

    /** @var list<string> */
    protected array $geometryColumns = ['location'];

    protected $fillable = [
        'code',
        'name',
        'name_alt',
        'basin_id',
        'sub_basin_id',
        'watershed_id',
        'latitude',
        'longitude',
        'elevation_m',
        'station_type',
        'station_class',
        'operator',
        'established_on',
        'data_source_id',
        'data_status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'elevation_m' => 'decimal:2',
            'established_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function basin(): BelongsTo
    {
        return $this->belongsTo(Basin::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StationCategory::class, 'station_type', 'code');
    }

    public function subBasin(): BelongsTo
    {
        return $this->belongsTo(SubBasin::class);
    }

    public function watershed(): BelongsTo
    {
        return $this->belongsTo(Watershed::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function stationParameters(): HasMany
    {
        return $this->hasMany(StationParameter::class);
    }

    public function parameters(): BelongsToMany
    {
        return $this->belongsToMany(Parameter::class, 'station_parameters')
            ->withPivot(['measurement_unit_id', 'sensor_reference', 'depth_m', 'starts_on', 'ends_on', 'is_active'])
            ->withTimestamps();
    }

    public function observations(): HasMany
    {
        return $this->hasMany(HydrologicalObservation::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(DataImportBatch::class, 'monitoring_station_id');
    }

    public function qualityIssues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
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
     * @param  Builder<MonitoringStation>  $query
     * @return Builder<MonitoringStation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<MonitoringStation>  $query
     * @param  list<string>  $classes
     * @return Builder<MonitoringStation>
     */
    public function scopeInClasses(Builder $query, array $classes): Builder
    {
        return $classes === [] ? $query : $query->whereIn('station_class', $classes);
    }

    /**
     * Presentation metadata for the observing network class.
     *
     * @return array{code: string, label: string, color: string, description: string}|null
     */
    public function classDefinition(): ?array
    {
        if ($this->station_class === null) {
            return null;
        }

        $definition = config('stations.classes.'.$this->station_class);

        if (! is_array($definition)) {
            return ['code' => $this->station_class, 'label' => ucfirst($this->station_class), 'color' => '#64748B', 'description' => ''];
        }

        return ['code' => $this->station_class, ...$definition];
    }

    public function classLabel(): string
    {
        return $this->classDefinition()['label'] ?? 'Unclassified';
    }
}
