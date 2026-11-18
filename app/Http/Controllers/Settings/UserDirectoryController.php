<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    private const SORT_COLUMNS = [
        'name',
        'email',
        'department',
        'created_at',
        'role',
    ];

    public function __construct(
        private readonly PermissionChecker $permissionChecker
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:invited,active,deactivated'],
            'role' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $roleId = $filters['role'] ?? null;
        $department = $filters['department'] ?? null;

        $requestedSort = $request->query('sort');
        $sort = in_array($requestedSort, self::SORT_COLUMNS, true)
            ? $requestedSort
            : 'name';

        $requestedDirection = $request->query('direction');
        $direction = in_array($requestedDirection, ['asc', 'desc'], true)
            ? $requestedDirection
            : 'asc';

        $usersQuery = User::query()
            ->with('role')
            ->when(
                $status === 'deactivated',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($term): void {
                    foreach ([
                        'name',
                        'email',
                        'contact_number',
                        'job_title',
                        'department',
                    ] as $column) {
                        $query->orWhereRaw(
                            "LOWER(COALESCE(users.{$column}, '')) LIKE ?",
                            [$term]
                        );
                    }
                });
            })
            ->when(
                in_array($status, ['invited', 'active'], true),
                fn (Builder $query) => $query->where('account_status', $status)
            )
            ->when($roleId !== null, function (Builder $query) use ($roleId): void {
                $query
                    ->where('role_id', $roleId)
                    ->whereHas('role', fn (Builder $roleQuery) => $roleQuery
                        ->where('scope', 'system'));
            })
            ->when(
                $department !== null && $department !== '',
                fn (Builder $query) => $query->where('department', $department)
            );

        if ($sort === 'role') {
            // Keep users without a system role last in both sort directions.
            $usersQuery->orderByRaw(
                'CASE WHEN EXISTS (
                        SELECT 1
                        FROM roles
                        WHERE roles.id = users.role_id
                          AND roles.scope = ?
                    ) THEN 0 ELSE 1 END ASC',
                ['system']
            );

            $usersQuery->orderBy(
                Role::query()
                    ->select('name')
                    ->whereColumn('roles.id', 'users.role_id')
                    ->where('scope', 'system'),
                $direction
            );
        } else {
            $usersQuery->orderBy($sort, $direction);
        }

        $users = $usersQuery
            ->with(['invitations' => fn ($query) => $query->latest('id')->limit(1)])
            ->orderBy('users.id')
            ->paginate(50)
            ->withQueryString();

        $systemRoles = Role::query()
            ->where('scope', 'system')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $departments = User::withTrashed()
            ->whereNotNull('department')
            ->where('department', '<>', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('settings.users', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'role' => $roleId,
            'department' => $department,
            'sort' => $sort,
            'direction' => $direction,
            'systemRoles' => $systemRoles,
            'departments' => $departments,
            'canManageAll' => $this->permissionChecker->isSuperAdmin($request->user()),
            'canManageInvitations' => $this->permissionChecker->allows($request->user(), 'users.invitations.manage'),
            'canAssignRoles' => $this->permissionChecker->allows($request->user(), 'users.roles.assign'),
        ]);
    }

    /**
     * Dedicated user details page: profile, account status, system role,
     * audit history, recent notification status, and the administrative
     * actions available to the current actor. Deactivated accounts remain
     * viewable; active management requires authorization on top of the
     * route middleware.
     */
    public function show(Request $request, string $user): View
    {
        $target = User::query()->withTrashed()->with('role')->findOrFail($user);

        $actor = $request->user();
        $canManageAll = $this->permissionChecker->isSuperAdmin($actor);

        $auditHistory = AuditLog::query()
            ->where('target_user_id', $target->getKey())
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $deliveries = NotificationDelivery::query()
            ->where('user_id', $target->getKey())
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Roles eligible for assignment: existing system-scoped roles (§6.1).
        // The SUPER_ADMIN role is only offered to Super Admin actors.
        $assignableRoles = Role::query()
            ->where('scope', 'system')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->reject(fn (Role $role): bool => $role->code === 'SUPER_ADMIN' && ! $canManageAll);

        $latestInvitation = $target->invitations()->latest('id')->first();

        // Mirror the server-side role-assignment rules so the form only
        // renders for actors the action would accept: the permission, the
        // self-change restriction, and the protected-target restrictions.
        $targetProtected = $target->role !== null
            && $target->role->scope === 'system'
            && in_array($target->role->code, ['SUPER_ADMIN', 'SYSTEM_ADMIN'], true);
        $canAssignRole = $this->permissionChecker->allows($actor, 'users.roles.assign')
            && ! $actor->is($target)
            && ($canManageAll || ! $targetProtected)
            && ! $target->trashed();

        // Mirror the server-side password-management rules (§11.2, §6.5) so
        // the card only renders for actors the actions would accept.
        $canManagePassword = $this->permissionChecker->allows($actor, 'users.password.manage')
            && ! $actor->is($target)
            && ($canManageAll || ! $targetProtected)
            && ! $target->trashed();

        return view('settings.user-details', [
            'target' => $target,
            'auditHistory' => $auditHistory,
            'deliveries' => $deliveries,
            'assignableRoles' => $assignableRoles,
            'latestInvitation' => $latestInvitation,
            'canManageAll' => $canManageAll,
            'canAssignRole' => $canAssignRole,
            'canManagePassword' => $canManagePassword,
        ]);
    }
}
