<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row-level failure raised while transforming an uploaded workbook.
 */
class DataImportError extends Model
{
    protected $fillable = [
        'data_import_batch_id',
        'row_number',
        'column_name',
        'error_code',
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
        return $this->belongsTo(DataImportBatch::class, 'data_import_batch_id');
    }
}
