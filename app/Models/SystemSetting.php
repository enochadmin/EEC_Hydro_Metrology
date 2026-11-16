<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored override. Rows exist only for values changed by an administrator;
 * defaults live in config/settings.php.
 */
class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'label',
        'type',
        'value',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
