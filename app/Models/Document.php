<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supporting file attached to a geographic record.
 *
 * The binary lives on the private `local` disk; the database keeps only the
 * relative path so storage can move without rewriting rows.
 */
class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'title',
        'original_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Lower-case extension for the file-type chip in the UI.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION)) ?: 'file';
    }
}
