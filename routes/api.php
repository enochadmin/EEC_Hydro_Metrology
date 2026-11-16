<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Token authentication is only used by machine clients. Browser navigation
| stays on Laravel's session guard through Inertia.
*/

Route::get('/v1/me', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->isActive(),
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
        ],
    ]);
})->middleware(['auth:sanctum', EnsureUserIsActive::class]);
