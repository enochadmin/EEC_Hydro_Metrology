<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationImportError extends Model
{
    protected $fillable = [
        'station_import_batch_id',
        'row_number',
        'column_name',
        'code',
        'severity',
        'message',
        'raw_value',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StationImportBatch::class, 'station_import_batch_id');
    }
}
