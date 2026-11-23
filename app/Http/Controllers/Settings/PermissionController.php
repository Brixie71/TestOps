<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Permission overview and display-metadata editing.
 *
 * Permission codes are referenced directly by authorization middleware and by
 * `PermissionChecker`, so they are immutable and permissions can never be
 * created or deleted here. Only the human-readable name and description are
 * editable, and every change is audited.
 */
class PermissionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $editingPermission = null;

        if ($request->filled('edit')) {
            $editingPermission = Permission::query()->find($request->integer('edit'));
        }

        return view('settings.permissions', [
            'permissions' => Permission::query()
                ->with('roles')
                ->orderBy('code')
                ->get(),
            'editingPermission' => $editingPermission,
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $permission, $validated): void {
            $permission = Permission::query()->lockForUpdate()->find($permission->getKey());

            if ($permission === null) {
                abort(404);
            }

            $before = [
                'name' => $permission->name,
                'description' => $permission->description,
            ];

            $permission->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ])->save();

            $this->auditLogger->log($request->user(), null, 'permission.updated', $before, [
                'permission_id' => $permission->getKey(),
                'code' => $permission->code,
                'name' => $permission->name,
                'description' => $permission->description,
            ]);
        });

        return redirect()
            ->route('settings.permissions')
            ->with('status', 'Permission updated.');
    }
}
