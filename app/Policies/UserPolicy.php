<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view users');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('view users');
    }

    public function create(User $user): bool
    {
        return $user->can('create users');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('edit users') && $this->canManageTarget($user, $target);
    }

    /**
     * Deactivation is separate from editing: it needs its own permission and
     * can never be applied to your own account.
     */
    public function deactivate(User $user, User $target): bool
    {
        return $user->can('deactivate users')
            && $user->id !== $target->id
            && $this->canManageTarget($user, $target);
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $user->can('assign roles') && $this->canManageTarget($user, $target);
    }

    /**
     * A Super Admin account can only be modified by another Super Admin.
     */
    private function canManageTarget(User $actor, User $target): bool
    {
        if ($target->isSuperAdmin()) {
            return $actor->isSuperAdmin();
        }

        return true;
    }
}
