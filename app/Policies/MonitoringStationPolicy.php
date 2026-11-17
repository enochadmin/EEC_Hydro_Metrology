<?php

namespace App\Policies;

use App\Models\MonitoringStation;
use App\Models\User;

class MonitoringStationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view stations');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MonitoringStation $monitoringStation): bool
    {
        return $user->can('view stations');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create stations');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MonitoringStation $monitoringStation): bool
    {
        return $user->can('edit stations');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MonitoringStation $monitoringStation): bool
    {
        return $user->can('delete stations');
    }

    public function manageDocuments(User $user, MonitoringStation $monitoringStation): bool
    {
        return $user->can('edit stations');
    }
}
