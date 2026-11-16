<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the append-only audit trail shown on the basin/sub-basin Activity tab.
 *
 * The actor is passed explicitly so the same logger works from queued jobs and
 * tests, with the authenticated request user as the fallback.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, ?Model $subject, string $description, array $properties = [], ?User $actor = null): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'user_id' => $actor?->getKey() ?? $request->user()?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }
}
