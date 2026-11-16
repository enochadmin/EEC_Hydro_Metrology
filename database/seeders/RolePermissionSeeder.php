<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permission catalogue and the seven EEC-BHDMS roles defined in
 * config/access.php. Safe to re-run: names are matched, not recreated.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalogue = collect(config('access.permissions'))
            ->flatten()
            ->unique()
            ->values();

        $catalogue->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        foreach (config('access.roles') as $roleName => $description) {
            $role = Role::findOrCreate($roleName, 'web');
            $granted = config('access.role_permissions.'.$roleName, []);

            $role->syncPermissions($granted === ['*'] ? $catalogue->all() : $granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Roles and permissions seeded: %d roles, %d permissions.',
            count(config('access.roles')),
            $catalogue->count(),
        ));
    }
}
