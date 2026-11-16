<?php

namespace App\Services\Access;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Business rules for account lifecycle and role assignment.
 *
 * Keeping these rules in one service means the controller, the API and any
 * future console command enforce the same guard rails.
 */
class UserAccountService
{
    public function __construct(private readonly ActivityLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     * @param  list<string>  $roles
     */
    public function create(array $attributes, array $roles, User $actor): User
    {
        $this->guardRoleAssignment($actor, $roles);

        return DB::transaction(function () use ($attributes, $roles): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles($roles);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null}  $attributes
     * @param  list<string>  $roles
     */
    public function update(User $user, array $attributes, array $roles, User $actor): User
    {
        $this->guardRoleAssignment($actor, $roles);
        $this->guardSelfRoleRemoval($actor, $user, $roles);

        return DB::transaction(function () use ($user, $attributes, $roles): User {
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
            ]);

            if (! empty($attributes['password'])) {
                $user->password = $attributes['password'];
            }

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            $user->syncRoles($roles);

            return $user;
        });
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot change the status of your own account.',
            ]);
        }

        if (! $active && $user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'At least one active Super Admin must remain in the system.',
            ]);
        }

        $user->forceFill([
            'is_active' => $active,
            'deactivated_at' => $active ? null : now(),
        ])->save();
    }

    /**
     * Only a Super Admin may grant or revoke the Super Admin role.
     *
     * @param  list<string>  $roles
     */
    private function guardRoleAssignment(User $actor, array $roles): void
    {
        $superAdminRole = config('access.super_admin_role');

        if (in_array($superAdminRole, $roles, true) && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'roles' => 'Only a Super Admin can grant the Super Admin role.',
            ]);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function guardSelfRoleRemoval(User $actor, User $target, array $roles): void
    {
        if ($actor->id !== $target->id) {
            return;
        }

        $superAdminRole = config('access.super_admin_role');

        if ($target->isSuperAdmin() && ! in_array($superAdminRole, $roles, true)) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot remove your own Super Admin role.',
            ]);
        }
    }

    public function activeSuperAdminCount(): int
    {
        return User::query()
            ->role(config('access.super_admin_role'))
            ->where('is_active', true)
            ->count();
    }
    /**
     * Set a new password on behalf of an administrator.
     *
     * The audit entry records that the reset happened and who asked for it; the
     * password itself is hashed and never written anywhere.
     */
    public function resetPassword(User $user, string $password, User $actor): void
    {
        DB::transaction(function () use ($user, $password, $actor): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => null,
            ])->save();

            $this->audit->log(
                'user.password_reset',
                $user,
                "Password reset for {$user->email}",
                ['email' => $user->email],
                $actor,
            );
        });
    }
}
