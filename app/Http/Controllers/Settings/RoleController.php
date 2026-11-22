<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Role administration for the Super Admin (§ RBAC: role definitions are
 * centrally managed by the Super Admin).
 *
 * The SUPER_ADMIN role is protected: it can never be renamed, re-described,
 * or have its permissions changed. Codes and scopes are immutable once a
 * role exists, and role deletion is not available yet. Every change is
 * audited.
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $editingRole = null;

        if ($request->filled('edit')) {
            $candidate = Role::query()
                ->with('permissions')
                ->find($request->integer('edit'));

            if ($candidate !== null && ! $this->isProtected($candidate)) {
                $editingRole = $candidate;
            }
        }

        return view('settings.roles', [
            'roles' => Role::query()
                ->with('permissions')
                ->orderBy('scope')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::query()->orderBy('code')->get(),
            'editingRole' => $editingRole,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:roles,code'],
            'scope' => ['required', 'string', Rule::in(['system', 'project'])],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => 'Use uppercase letters, numbers, and underscores, starting with a letter.',
            'code.unique' => 'A role with that code already exists.',
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $role = Role::query()->create($validated);

            $this->auditLogger->log($request->user(), null, 'role.created', null, [
                'role_id' => $role->getKey(),
                'name' => $role->name,
                'code' => $role->code,
                'scope' => $role->scope,
                'description' => $role->description,
            ]);
        });

        return redirect()
            ->route('settings.roles')
            ->with('status', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($this->isProtected($role)) {
            abort(403, 'The SUPER_ADMIN role cannot be modified.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        DB::transaction(function () use ($request, $role, $validated): void {
            $role = Role::query()->lockForUpdate()->find($role->getKey());

            if ($role === null) {
                abort(404);
            }

            if ($this->isProtected($role)) {
                abort(403, 'The SUPER_ADMIN role cannot be modified.');
            }

            $before = [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions()->orderBy('code')->pluck('code')->all(),
            ];

            $role->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ])->save();

            $role->permissions()->sync($validated['permissions'] ?? []);

            $after = [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions()->orderBy('code')->pluck('code')->all(),
            ];

            $this->auditLogger->log($request->user(), null, 'role.updated', $before, $after);
        });

        return redirect()
            ->route('settings.roles')
            ->with('status', 'Role updated.');
    }

    private function isProtected(Role $role): bool
    {
        return $role->code === 'SUPER_ADMIN' && $role->scope === 'system';
    }
}
