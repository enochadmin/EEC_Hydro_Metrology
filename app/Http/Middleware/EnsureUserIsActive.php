<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends an authenticated session as soon as the account is deactivated, so a
 * revoked user cannot keep working from an open browser tab.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                abort(403, 'This account has been deactivated.');
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This account has been deactivated. Contact a system administrator.']);
        }

        return $next($request);
    }
}
