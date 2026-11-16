<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit trail browser.
 *
 * The log is append-only and excludes credentials by construction: the logger
 * only accepts a description and a property array, and callers never pass
 * passwords or tokens. This screen therefore cannot leak what was never stored.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view activity logs'), 403);

        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:50'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['user'] ?? null, fn ($query, int $id) => $query->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'subject' => $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : null,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Administration/ActivityLogs/Index', [
            'logs' => $logs,
            'events' => ActivityLog::query()
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'event' => $filters['event'] ?? '',
                'user' => $filters['user'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'summary' => [
                'total' => ActivityLog::query()->count(),
                'today' => ActivityLog::query()->where('created_at', '>=', now()->startOfDay())->count(),
                'actors' => ActivityLog::query()->distinct('user_id')->count('user_id'),
            ],
        ]);
    }
}
