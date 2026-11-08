<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * End the session of authenticated users whose account is no longer active.
 *
 * Runs after `auth` on authenticated web routes. When the authenticated user
 * is soft-deleted or their `account_status` is not `active`, the web guard is
 * logged out, the session is invalidated, and the CSRF token is regenerated
 * before redirecting to the login route.
 */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ($user->trashed() || $user->account_status !== 'active')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login');
        }

        return $next($request);
    }
}
