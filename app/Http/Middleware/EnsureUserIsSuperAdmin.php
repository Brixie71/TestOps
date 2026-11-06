<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker
    ) {}

    /**
     * Allow only authenticated users whose assigned system-level role
     * is Super Admin, verified by role code and scope — never by name/email.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->permissionChecker->isSuperAdmin($user)) {
            abort(403);
        }

        return $next($request);
    }
}
