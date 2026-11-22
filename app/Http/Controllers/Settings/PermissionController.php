<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Contracts\View\View;

/**
 * Read-only permission overview.
 *
 * Permission and role mutations are not approved yet, so this page only
 * exposes the registered permissions and the roles that grant them.
 */
class PermissionController extends Controller
{
    public function index(): View
    {
        return view('settings.permissions', [
            'permissions' => Permission::query()
                ->with('roles')
                ->orderBy('code')
                ->get(),
        ]);
    }
}
