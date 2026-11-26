<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One station-registry workbook: upload, staged preview and committed import.
 *
 * Parsed rows live on the private disk (`staged_path`); only the mapping,
 * summary and counters are persisted here so a preview can be reviewed,
 * re-previewed with different options and finally committed.
 */
class StationImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'file_name',
        'file_path',
        'file_size',
        'checksum',
        'uploaded_by',
        'imported_by',
        'status',
        'stage',
        'options',
        'mapping',
        'summary',
        'staged_path',
        'total_rows',
        'importable_rows',
        'imported_rows',
        'duplicate_rows',
        'skipped_rows',
        'error_rows',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'mapping' => 'array',
            'summary' => 'array',
            'file_size' => 'integer',
            'total_rows' => 'integer',
            'importable_rows' => 'integer',
            'imported_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'skipped_rows' => 'integer',
            'error_rows' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(StationImportError::class);
    }
}
