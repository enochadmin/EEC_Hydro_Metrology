<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Access\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly UserAccountService $accounts) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $search = $filters['search'] ?? null;
        $role = $filters['role'] ?? null;
        $status = $filters['status'] ?? null;

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($role, fn ($query, string $name) => $query->role($name))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'roles' => $user->roles->pluck('name')->all(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search ?? '',
                'role' => $role ?? '',
                'status' => $status ?? '',
            ],
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'summary' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('is_active', true)->count(),
                'inactive' => User::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->accounts->create($request->validated(), $request->validated('roles'), $request->user());

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'roles' => $user->roles->pluck('name')->all(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roles' => $this->roleOptions(),
            'canDeactivate' => Gate::allows('deactivate', $user),
            'isSelf' => $request->user()->id === $user->id,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->accounts->update($user, $request->validated(), $request->validated('roles'), $request->user());

        return redirect()->route('users.edit', $user)->with('success', 'User account updated.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->accounts->setActive($user, (bool) $validated['is_active'], $request->user());

        return back()->with('success', $validated['is_active'] ? 'Account reactivated.' : 'Account deactivated.');
    }

    /**
     * @return list<array{name: string, description: string|null, users_count: int, is_locked: bool}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'description' => config('access.roles.'.$role->name),
                'users_count' => $role->users_count,
                'is_locked' => $role->name === config('access.super_admin_role'),
            ])
            ->all();
    }
    /**
     * Account detail with its audit trail. Passwords, tokens and session
     * identifiers are never part of the payload.
     */
    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $activity = ActivityLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'event', 'description', 'properties', 'ip_address', 'created_at'])
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Users/Show', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'roles' => $user->roles->pluck('name')->all(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'activity' => $activity,
            'summary' => [
                'events' => ActivityLog::query()->where('user_id', $user->id)->count(),
                'last_event_at' => ActivityLog::query()->where('user_id', $user->id)->max('created_at'),
            ],
            'canManage' => [
                'edit' => Gate::allows('update', $user),
                'deactivate' => Gate::allows('deactivate', $user),
                'reset_password' => $request->user()->can('edit users'),
            ],
        ]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->accounts->resetPassword($user, $request->validated('password'), $request->user());

        return back()->with('success', 'Password reset. The account must sign in with the new password.');
    }
}
