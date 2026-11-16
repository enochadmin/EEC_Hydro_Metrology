<?php

namespace App\Http\Controllers\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\Access\RoleAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage permissions'), 403);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description ?: config('access.roles.'.$role->name),
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'is_locked' => $role->name === config('access.super_admin_role'),
            ]);

        return Inertia::render('AccessControl/Roles/Index', [
            'roles' => $roles,
            'permissionGroups' => config('access.permissions'),
            'totalPermissions' => Permission::query()->count(),
        ]);
    }

    public function edit(Request $request, Role $role): Response
    {
        abort_unless($request->user()->can('manage permissions'), 403);

        return Inertia::render('AccessControl/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description ?: config('access.roles.'.$role->name),
                'permissions' => $role->permissions()->pluck('name')->all(),
                'users_count' => $role->users()->count(),
                'is_locked' => $role->name === config('access.super_admin_role'),
            ],
            'permissionGroups' => config('access.permissions'),
        ]);
    }


    /**
     * Stop an administrator from removing the capability that lets them
     * manage access while editing a role they hold themselves.
     *
     * @param  list<string>  $permissions
     */
    private function guardOwnAccessManagement(Request $request, Role $role, array $permissions): void
    {
        $actor = $request->user();

        if ($actor->isSuperAdmin() || ! $actor->hasRole($role->name)) {
            return;
        }

        if (! in_array('manage permissions', $permissions, true)) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot remove the access-management permission from a role you hold.',
            ]);
        }
    }
    public function store(StoreRoleRequest $request, RoleAdministrationService $roles): RedirectResponse
    {
        abort_unless($request->user()->can('manage permissions'), 403);

        $roles->create($request->validated(), $request->validated('permissions', []), $request->user());

        return redirect()->route('access.roles.index')->with('success', 'Role created.');
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleAdministrationService $roles): RedirectResponse
    {
        abort_unless($request->user()->can('manage permissions'), 403);

        $permissions = $request->validated('permissions', []);

        $this->guardOwnAccessManagement($request, $role, $permissions);

        $attributes = [
            'name' => $request->validated('name', $role->name),
            'description' => $request->validated('description', $role->description),
        ];

        $roles->update($role, $attributes, $permissions, $request->user());

        return redirect()->route('access.roles.edit', $role)->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Role $role, RoleAdministrationService $roles): RedirectResponse
    {
        abort_unless($request->user()->can('manage permissions'), 403);

        $roles->delete($role, $request->user());

        return redirect()->route('access.roles.index')->with('success', 'Role deleted.');
    }
}
