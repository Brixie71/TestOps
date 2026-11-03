<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * Allow only authenticated users whose assigned system-level role
     * is Super Admin, verified by role code and scope — never by name/email.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || ! $user->role
            || $user->role->code !== 'SUPER_ADMIN'
            || $user->role->scope !== 'system'
        ) {
            abort(403);
        }

        return $next($request);
    }
}
