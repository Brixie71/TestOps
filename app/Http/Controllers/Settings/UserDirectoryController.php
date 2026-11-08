<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:invited,active,deactivated'],
        ]);

        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $users = User::query()
            ->with('role')
            // Deactivated (soft-deleted) accounts remain visible through the
            // dedicated `deactivated` filter, per the approved specification.
            ->when($status === 'deactivated', fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->when(in_array($status, ['invited', 'active'], true), function (Builder $query) use ($status): void {
                $query->where('account_status', $status);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('settings.users', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            // Super Admin may target every account; other authorized lifecycle
            // actors may not manage SUPER_ADMIN or SYSTEM_ADMIN accounts, and
            // the view hides those lifecycle controls accordingly.
            'canManageAll' => $this->permissionChecker->isSuperAdmin($request->user()),
        ]);
    }
}
