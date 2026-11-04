<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $this->permissionChecker->allows($user, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
