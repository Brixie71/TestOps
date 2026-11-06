<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:invited,active'],
        ]);

        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $users = User::query()
            ->with('role')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->when($status !== null && $status !== '', function (Builder $query) use ($status): void {
                $query->where('account_status', $status);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('settings.users', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
