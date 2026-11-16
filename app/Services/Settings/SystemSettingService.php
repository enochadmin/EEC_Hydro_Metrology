<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads, writes and applies administrator settings.
 *
 * The registry in config/settings.php is the contract: it declares each key's
 * type, default and the config path it feeds. Stored values override the
 * default, and `apply()` copies the result into the config repository, so the
 * rest of the application keeps reading `config(...)` and never has to know a
 * setting exists.
 */
class SystemSettingService
{
    public function __construct(private readonly ActivityLogger $audit) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function registry(): array
    {
        return config('settings.groups', []);
    }

    /**
     * Every registered key with its effective value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $result = [];

        foreach ($this->registry() as $groupKey => $group) {
            foreach ($group['settings'] as $key => $definition) {
                $result[$key] = [
                    ...$definition,
                    'key' => $key,
                    'group' => $groupKey,
                    'value' => $stored[$key] ?? $this->cast($definition['default'], $definition['type']),
                    'is_default' => ! array_key_exists($key, $stored),
                ];
            }
        }

        return $result;
    }

    public function get(string $key): mixed
    {
        $settings = $this->all();

        return $settings[$key]['value'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values, User $actor): void
    {
        $registry = $this->all();

        DB::transaction(function () use ($values, $registry, $actor): void {
            foreach ($values as $key => $value) {
                if (! isset($registry[$key])) {
                    continue;
                }

                $definition = $registry[$key];

                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $definition['group'],
                        'label' => $definition['label'],
                        'type' => $definition['type'],
                        'value' => $this->cast($value, $definition['type']),
                        'description' => $definition['description'] ?? null,
                        'updated_by' => $actor->getKey(),
                    ],
                );

                $this->audit->log(
                    'settings.updated',
                    null,
                    "Setting {$definition['label']} changed",
                    ['key' => $key, 'value' => $this->cast($value, $definition['type'])],
                    $actor,
                );
            }
        });

        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(config('settings.cache_key'));
    }

    /**
     * Push stored values into the config repository.
     */
    public function apply(): void
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }

            foreach ($this->all() as $key => $definition) {
                if (empty($definition['config'])) {
                    continue;
                }

                config([$definition['config'] => $definition['value']]);
            }
        } catch (\Throwable) {
            // Fail gracefully if database connection is unavailable during early bootstrap or CLI
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $cached = Cache::remember(
            config('settings.cache_key'),
            config('settings.cache_ttl'),
            fn () => SystemSetting::query()->pluck('value', 'key')->all()
        );

        return is_array($cached) ? $cached : [];
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => (bool) $value,
            default => $value,
        };
    }
}
