<?php

namespace App\Services\Access;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates, renames and removes roles, and records every change.
 *
 * Two rules protect the access model: the Super Admin role is never editable
 * (it is bound to a Gate bypass), and a role that still has members cannot be
 * deleted, because that would silently strip their access. A non-super-admin
 * may not grant a permission they do not hold themselves.
 */
class RoleAdministrationService
{
    public function __construct(private readonly ActivityLogger $audit) {}

    /**
     * @param  array{name: string, description?: string|null}  $attributes
     * @param  list<string>  $permissions
     */
    public function create(array $attributes, array $permissions, User $actor): Role
    {
        $this->guardName($attributes['name'], $actor);

        return DB::transaction(function () use ($attributes, $permissions, $actor): Role {
            $role = Role::create([
                'name' => $attributes['name'],
                'guard_name' => 'web',
                'description' => $attributes['description'] ?? null,
            ]);

            $role->syncPermissions($this->allowedPermissions($permissions, $actor));

            $this->audit->log('role.created', null, "Role {$role->name} created", [
                'role' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ], $actor);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * @param  array{name: string, description?: string|null}  $attributes
     * @param  list<string>  $permissions
     */
    public function update(Role $role, array $attributes, array $permissions, User $actor): Role
    {
        if ($role->name === config('access.super_admin_role')) {
            throw ValidationException::withMessages([
                'name' => 'The Super Admin role cannot be modified; it is bound to the unrestricted bypass.',
            ]);
        }

        return DB::transaction(function () use ($role, $attributes, $permissions, $actor): Role {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();

            $role->update([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
            ]);

            $role->syncPermissions($this->allowedPermissions($permissions, $actor));

            $after = $role->permissions()->pluck('name')->sort()->values()->all();

            $this->audit->log('role.updated', null, "Role {$role->name} updated", [
                'role' => $role->name,
                'granted' => array_values(array_diff($after, $before)),
                'removed' => array_values(array_diff($before, $after)),
            ], $actor);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->refresh();
        });
    }

    public function delete(Role $role, User $actor): void
    {
        if ($role->name === config('access.super_admin_role')) {
            throw ValidationException::withMessages([
                'role' => 'The Super Admin role cannot be deleted.',
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Reassign the users of this role before deleting it.',
            ]);
        }

        DB::transaction(function () use ($role, $actor): void {
            $name = $role->name;
            $role->delete();

            $this->audit->log('role.deleted', null, "Role {$name} deleted", ['role' => $name], $actor);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Only a Super Admin may hand out a permission they do not hold.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function allowedPermissions(array $permissions, User $actor): array
    {
        $catalogue = collect(config('access.permissions'))->flatten()->unique();

        $permissions = collect($permissions)->filter(fn ($name) => $catalogue->contains($name))->unique()->values();

        if ($actor->hasRole(config('access.super_admin_role'))) {
            return $permissions->all();
        }

        $grantable = $permissions->filter(fn (string $name) => $actor->can($name))->values();

        if ($grantable->count() !== $permissions->count()) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot grant a permission you do not hold yourself.',
            ]);
        }

        return $grantable->all();
    }

    private function guardName(string $name, User $actor): void
    {
        if (trim($name) === '') {
            throw ValidationException::withMessages(['name' => 'A role name is required.']);
        }

        if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
            throw ValidationException::withMessages(['name' => 'A role with that name already exists.']);
        }

        if (! $actor->hasRole(config('access.super_admin_role')) && Role::count() >= 50) {
            throw ValidationException::withMessages(['name' => 'The role catalogue is at its limit.']);
        }
    }
}
