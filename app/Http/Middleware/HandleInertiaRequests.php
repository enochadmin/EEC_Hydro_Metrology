<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'is_super_admin' => $user->isSuperAdmin(),
                    // Populated once Spatie role management is installed in Phase 2.
                    'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [],
                    'permissions' => method_exists($user, 'getAllPermissions')
                        ? $user->getAllPermissions()->pluck('name')->all()
                        : [],
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'application' => [
                'name' => config('app.name'),
                'short_name' => config('eec.app.short_name'),
                'full_name' => config('eec.app.full_name'),
                'version' => config('eec.app.version'),
                'phase' => config('eec.app.phase'),
                'environment' => app()->environment(),
            ],
        ];
    }
}
