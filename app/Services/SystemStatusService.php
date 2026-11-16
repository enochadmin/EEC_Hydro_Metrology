<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Collects runtime facts about the installation for the dashboard
 * "System health" panel. Every value is read from the running application;
 * nothing is simulated.
 */
class SystemStatusService
{
    /**
     * @return array{checked_at: string, items: list<array{key: string, label: string, value: string, state: string}>}
     */
    public function snapshot(): array
    {
        return [
            'checked_at' => now()->toIso8601String(),
            'items' => [
                $this->database(),
                $this->storage(),
                [
                    'key' => 'environment',
                    'label' => 'Environment',
                    'value' => app()->environment(),
                    'state' => app()->isProduction() ? 'ok' : 'warning',
                ],
                [
                    'key' => 'runtime',
                    'label' => 'Runtime',
                    'value' => 'PHP '.PHP_VERSION.' · Laravel '.app()->version(),
                    'state' => 'ok',
                ],
                [
                    'key' => 'timezone',
                    'label' => 'Timezone',
                    'value' => config('app.timezone'),
                    'state' => 'ok',
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, value: string, state: string}
     */
    private function database(): array
    {
        try {
            $version = DB::selectOne('select version() as version')?->version;
            $connection = config('database.default');

            return [
                'key' => 'database',
                'label' => 'Database',
                'value' => trim(($version ?? 'Unknown').' · '.$connection),
                'state' => 'ok',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'key' => 'database',
                'label' => 'Database',
                'value' => 'Unavailable',
                'state' => 'error',
            ];
        }
    }

    /**
     * @return array{key: string, label: string, value: string, state: string}
     */
    private function storage(): array
    {
        $writable = is_writable(storage_path()) && is_writable(storage_path('app'));

        return [
            'key' => 'storage',
            'label' => 'Storage',
            'value' => $writable ? 'Writable' : 'Not writable',
            'state' => $writable ? 'ok' : 'error',
        ];
    }
}
