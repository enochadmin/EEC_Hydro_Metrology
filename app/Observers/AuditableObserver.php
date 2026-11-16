<?php

namespace App\Observers;

use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes create / update / delete entries for the records the brief requires.
 *
 * Only changed attributes are logged, and a deny-list removes anything that
 * could carry a credential or a token, so an audit entry can never become the
 * place a secret leaks.
 */
class AuditableObserver
{
    /**
     * Attribute names that must never reach the log.
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function __construct(private readonly ActivityLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->log(
            strtolower(class_basename($model)).'.created',
            $model,
            class_basename($model).' created',
            ['attributes' => $this->safe($model->getAttributes())],
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->safe($model->getChanges());

        // Nothing meaningful changed (for example only `updated_at`).
        if ($changes === []) {
            return;
        }

        $this->audit->log(
            strtolower(class_basename($model)).'.updated',
            $model,
            class_basename($model).' updated',
            ['changes' => $changes],
        );
    }

    public function deleted(Model $model): void
    {
        $this->audit->log(
            strtolower(class_basename($model)).'.deleted',
            $model,
            class_basename($model).' deleted',
            ['attributes' => $this->safe($model->getAttributes())],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function safe(array $attributes): array
    {
        unset($attributes['updated_at'], $attributes['created_at']);

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = '[redacted]';
            }
        }

        return $attributes;
    }
}
