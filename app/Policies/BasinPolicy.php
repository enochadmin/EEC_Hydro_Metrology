<?php

namespace App\Policies;

use App\Models\Basin;
use App\Models\User;

/**
 * Basin authorisation.
 *
 * Route middleware already blocks the request; the policy is the second layer
 * and the only one consulted by `Gate::authorize` / `@can` style checks.
 * Boundary import is deliberately separate from editing: a GIS Officer may
 * upload polygons without being allowed to change the registry record.
 */
class BasinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view basins');
    }

    public function view(User $user, Basin $basin): bool
    {
        return $user->can('view basins');
    }

    public function create(User $user): bool
    {
        return $user->can('create basins');
    }

    public function update(User $user, Basin $basin): bool
    {
        return $user->can('edit basins');
    }

    public function delete(User $user, Basin $basin): bool
    {
        return $user->can('delete basins');
    }

    public function importBoundary(User $user, Basin $basin): bool
    {
        return $user->can('import basin boundaries');
    }

    public function manageDocuments(User $user, Basin $basin): bool
    {
        return $user->can('edit basins');
    }

    public function export(User $user): bool
    {
        return $user->can('view basins');
    }
}
