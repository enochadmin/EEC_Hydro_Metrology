<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'checksum',
        'data_source_id',
        'monitoring_station_id',
        'uploaded_by',
        'status',
        'total_rows',
        'imported_rows',
        'duplicate_rows',
        'rejected_rows',
        'started_at',
        'completed_at',
        'notes',
        'stage',
        'sheet_name',
        'structure_type',
        'mapping',
        'summary',
        'validation_summary',
        'comparison',
        'station_map',
        'transformed_path',
        'validation_path',
        'original_rows',
        'original_columns',
        'approved_at',
        'approved_by',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'rejected_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'mapping' => 'array',
            'summary' => 'array',
            'validation_summary' => 'array',
            'comparison' => 'array',
            'station_map' => 'array',
            'original_rows' => 'integer',
            'original_columns' => 'integer',
            'approved_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(MonitoringStation::class, 'monitoring_station_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(DataImportError::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(HydrologicalObservation::class);
    }

    public function qualityIssues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
    }
}
