<?php

namespace App\Services\Geography;

use App\Models\SubBasin;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubBasinService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): SubBasin
    {
        return DB::transaction(function () use ($attributes, $actor): SubBasin {
            $subBasin = SubBasin::create($this->normalise($attributes));
            $subBasin->load('basin:id,code,name');

            $this->logger->log(
                'sub-basin.created',
                $subBasin,
                'Sub-basin '.$subBasin->code.' — '.$subBasin->name.' registered under '.$subBasin->basin->code.'.',
                ['code' => $subBasin->code, 'basin_id' => $subBasin->basin_id],
                $actor,
            );

            return $subBasin;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SubBasin $subBasin, array $attributes, User $actor): SubBasin
    {
        return DB::transaction(function () use ($subBasin, $attributes, $actor): SubBasin {
            $previousBasin = $subBasin->basin_id;

            $subBasin->fill($this->normalise($attributes));
            $changed = array_values(array_diff(array_keys($subBasin->getDirty()), ['updated_at']));
            $subBasin->save();

            if ($changed !== []) {
                $this->logger->log(
                    'sub-basin.updated',
                    $subBasin,
                    'Sub-basin '.$subBasin->code.' updated.',
                    [
                        'changed' => $changed,
                        'previous_basin_id' => $previousBasin,
                        'basin_id' => $subBasin->basin_id,
                    ],
                    $actor,
                );
            }

            return $subBasin->refresh()->load('basin:id,code,name');
        });
    }

    public function delete(SubBasin $subBasin, User $actor): void
    {
        $stations = $subBasin->stations()->count();

        if ($stations > 0) {
            throw ValidationException::withMessages([
                'sub-basin' => sprintf(
                    'This sub-basin still has %d monitoring station(s). Reassign them to another sub-basin before deleting it.',
                    $stations,
                ),
            ]);
        }

        DB::transaction(function () use ($subBasin, $actor): void {
            $this->logger->log(
                'sub-basin.deleted',
                $subBasin,
                'Sub-basin '.$subBasin->code.' deleted (soft delete, recoverable).',
                ['code' => $subBasin->code, 'basin_id' => $subBasin->basin_id],
                $actor,
            );

            $subBasin->delete();
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
