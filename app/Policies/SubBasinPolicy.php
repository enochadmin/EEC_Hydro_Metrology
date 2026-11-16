<?php

namespace App\Policies;

use App\Models\SubBasin;
use App\Models\User;

class SubBasinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view sub-basins');
    }

    public function view(User $user, SubBasin $subBasin): bool
    {
        return $user->can('view sub-basins');
    }

    public function create(User $user): bool
    {
        return $user->can('create sub-basins');
    }

    public function update(User $user, SubBasin $subBasin): bool
    {
        return $user->can('edit sub-basins');
    }

    public function delete(User $user, SubBasin $subBasin): bool
    {
        return $user->can('delete sub-basins');
    }

    /**
     * Polygon import reuses the basin boundary permission; there is no separate
     * sub-basin boundary permission in the Phase 2 catalogue.
     */
    public function importBoundary(User $user, SubBasin $subBasin): bool
    {
        return $user->can('import basin boundaries');
    }

    public function manageDocuments(User $user, SubBasin $subBasin): bool
    {
        return $user->can('edit sub-basins');
    }

    public function export(User $user): bool
    {
        return $user->can('view sub-basins');
    }
}
