<?php

namespace App\Services\Geography;

use App\Models\Basin;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BasinService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): Basin
    {
        return DB::transaction(function () use ($attributes, $actor): Basin {
            $basin = Basin::create($this->normalise($attributes));

            $this->logger->log(
                'basin.created',
                $basin,
                'Basin '.$basin->code.' — '.$basin->name.' registered.',
                ['code' => $basin->code, 'data_status' => $basin->data_status],
                $actor,
            );

            return $basin;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Basin $basin, array $attributes, User $actor): Basin
    {
        return DB::transaction(function () use ($basin, $attributes, $actor): Basin {
            $basin->fill($this->normalise($attributes));
            $changed = array_values(array_diff(array_keys($basin->getDirty()), ['updated_at']));
            $basin->save();

            if ($changed !== []) {
                $this->logger->log(
                    'basin.updated',
                    $basin,
                    'Basin '.$basin->code.' updated.',
                    ['changed' => $changed],
                    $actor,
                );
            }

            return $basin->refresh();
        });
    }

    /**
     * Soft-delete a basin only when it has no geographic children.
     *
     * Sub-basins and stations use restrictive foreign keys, so silently
     * archiving the parent would leave records pointing at an invisible basin.
     * The guard keeps the hierarchy readable instead.
     */
    public function delete(Basin $basin, User $actor): void
    {
        $subBasins = $basin->subBasins()->count();
        $stations = $basin->stations()->count();

        if ($subBasins > 0 || $stations > 0) {
            throw ValidationException::withMessages([
                'basin' => sprintf(
                    'This basin still has %d sub-basin(s) and %d monitoring station(s). Reassign or remove them before deleting the basin.',
                    $subBasins,
                    $stations,
                ),
            ]);
        }

        DB::transaction(function () use ($basin, $actor): void {
            $this->logger->log(
                'basin.deleted',
                $basin,
                'Basin '.$basin->code.' deleted (soft delete, recoverable).',
                ['code' => $basin->code],
                $actor,
            );

            $basin->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $attributes['code'] = Str::upper(trim((string) $attributes['code']));

        return $attributes;
    }
}
