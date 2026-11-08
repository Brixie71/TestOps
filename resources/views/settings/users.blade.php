@extends('layouts.settings')

@section('title', 'Users | TestOps')

@section('settings-content')
@php
    /*
     * Explicit filter detection. PHP treats the string '0' as falsy, so a
     * truthiness check ($search || $status) would silently ignore a
     * legitimate search for "0".
     *
     * Pagination links preserve filters via the controller's
     * withQueryString() — no view-side appends() is needed or wanted.
     */
    $hasFilters = ($search !== null && $search !== '')
        || ($status !== null && $status !== '');

    $currentPage = $users->currentPage();
    $lastPage = $users->lastPage();

    // Windowed page list, e.g. 1 … 4 5 6 … 12 (all pages when 7 or fewer).
    $pageWindow = $lastPage <= 7
        ? collect(range(1, $lastPage))
        : collect([1, $lastPage])
            ->merge(range(max(2, $currentPage - 1), min($lastPage - 1, $currentPage + 1)))
            ->sort()
            ->unique()
            ->values();
@endphp

    <section aria-labelledby="users-heading" class="space-y-6">
        <header>
            <h2 id="users-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Users
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                View and search TestOps user accounts and their assigned system roles.
            </p>
        </header>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {{-- Search and status filters (GET) --}}
            <div class="border-b border-slate-200 p-4 dark:border-slate-800 sm:p-5">
                <form method="GET" action="{{ route('settings.users') }}" class="flex flex-col gap-3 sm:flex-row">
                    <div class="min-w-0 flex-1">
                        <label for="user-search" class="sr-only">Search users by name or email</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path stroke-linecap="round" d="m16 16 4 4" />
                            </svg>
                            <input
                                id="user-search"
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Search by name or email..."
                                class="block w-full rounded-lg border border-slate-200 bg-white py-2.5 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="sm:w-44">
                        <label for="user-status" class="sr-only">Filter by account status</label>
                        <select
                            id="user-status"
                            name="status"
                            class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                        >
                            <option value="" @selected($status === null || $status === '')>All statuses</option>
                            <option value="invited" @selected($status === 'invited')>Invited</option>
                            <option value="active" @selected($status === 'active')>Active</option>
                            <option value="deactivated" @selected($status === 'deactivated')>Deactivated</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                        >
                            Search
                        </button>

                        @if ($hasFilters)
                            <a
                                href="{{ route('settings.users') }}"
                                class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                            >
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Results summary --}}
            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">User directory</p>
                <p class="text-xs text-slate-500 dark:text-slate-400" aria-live="polite">
                    Showing {{ $users->firstItem() ?? 0 }}&ndash;{{ $users->lastItem() ?? 0 }}
                    of {{ $users->total() }} {{ Str::plural('user', $users->total()) }}
                </p>
            </div>

            @if ($users->isNotEmpty())
                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <caption class="sr-only">TestOps user accounts</caption>
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-5 py-3">User</th>
                                <th scope="col" class="px-5 py-3">Email</th>
                                <th scope="col" class="px-5 py-3">System role</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($users as $user)
                                @php
                                    $initial = mb_strtoupper(mb_substr(trim((string) $user->name), 0, 1)) ?: 'U';

                                    // Mirror the server-side eligibility rules: only
                                    // Super Admins (or actors managing non-protected
                                    // targets) see lifecycle controls for a row.
                                    $targetRole = $user->role;
                                    $targetProtected = $targetRole !== null
                                        && $targetRole->scope === 'system'
                                        && in_array($targetRole->code, ['SUPER_ADMIN', 'SYSTEM_ADMIN'], true);
                                    $lifecycleAllowed = $canManageAll || ! $targetProtected;
                                @endphp
                                <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">
                                                {{ $initial }}
                                            </span>
                                            <span class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ $user->name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                        {{ $user->email }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($user->role?->scope === 'system')
                                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                                {{ $user->role->name }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">No system role</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($user->trashed())
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400" aria-hidden="true"></span>
                                                Deactivated
                                            </span>
                                        @elseif ($user->account_status === 'active')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                                Active
                                            </span>
                                        @elseif ($user->account_status === 'invited')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                                Invited
                                            </span>
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">
                                                {{ ucfirst((string) $user->account_status) ?: 'Unknown' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($user->trashed() && $lifecycleAllowed)
                                            {{-- Reactivate a deactivated account. --}}
                                            <form method="POST" action="{{ route('settings.users.reactivate', $user->id) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                                                >
                                                    Reactivate
                                                </button>
                                            </form>
                                        @elseif (! $user->trashed() && ! $user->is(auth()->user()) && $lifecycleAllowed)
                                            {{-- Deactivate an eligible account; the reason is required server-side. --}}
                                            <details class="group">
                                                <summary class="inline-flex cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white">
                                                    Deactivate
                                                </summary>
                                                <form method="POST" action="{{ route('settings.users.deactivate', $user->id) }}" class="mt-2 flex flex-col gap-2">
                                                    @csrf
                                                    <label for="deactivation-reason-{{ $user->id }}" class="sr-only">Deactivation reason for {{ $user->name }}</label>
                                                    <textarea
                                                        id="deactivation-reason-{{ $user->id }}"
                                                        name="reason"
                                                        rows="2"
                                                        maxlength="500"
                                                        required
                                                        placeholder="Reason (required)"
                                                        class="block w-full min-w-56 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"></textarea>
                                                    @error('reason')
                                                        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                                    @enderror
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition-colors hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 dark:border-rose-900/70 dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/20"
                                                    >
                                                        Confirm deactivation
                                                    </button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <nav aria-label="User directory pagination" class="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Page {{ $currentPage }} of {{ $lastPage }}
                        </p>

                        <div class="flex items-center gap-1.5">
                            @if ($users->onFirstPage())
                                <span aria-disabled="true" class="inline-flex h-9 cursor-not-allowed items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-400 opacity-60 dark:border-slate-700 dark:text-slate-600">
                                    Previous
                                </span>
                            @else
                                <a href="{{ $users->previousPageUrl() }}" rel="prev" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                    Previous
                                </a>
                            @endif

                            {{-- Page numbers hidden on the smallest screens; Prev/Next always remain --}}
                            <div class="hidden items-center gap-1.5 md:flex">
                                @foreach ($pageWindow as $page)
                                    @if (! $loop->first && $page - $pageWindow[$loop->index - 1] > 1)
                                        <span aria-hidden="true" class="px-1 text-sm text-slate-400 dark:text-slate-600">&hellip;</span>
                                    @endif

                                    @if ($page === $currentPage)
                                        <span aria-current="page" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-2 text-sm font-semibold text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300">
                                            {{ $page }}
                                        </span>
                                    @else
                                        <a href="{{ $users->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                            {{ $page }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>

                            @if ($users->hasMorePages())
                                <a href="{{ $users->nextPageUrl() }}" rel="next" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                    Next
                                </a>
                            @else
                                <span aria-disabled="true" class="inline-flex h-9 cursor-not-allowed items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-400 opacity-60 dark:border-slate-700 dark:text-slate-600">
                                    Next
                                </span>
                            @endif
                        </div>
                    </nav>
                @endif
            @else
                <div class="border-t border-slate-200 px-5 py-14 text-center dark:border-slate-800">
                    <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <circle cx="11" cy="8" r="3.25" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 19a6 6 0 0112 0M19 8v6m-3-3h6" />
                        </svg>
                    </span>

                    @if ($hasFilters)
                        <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
                            No matching users
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            No accounts match the current search or status filter.
                        </p>
                        <a
                            href="{{ route('settings.users') }}"
                            class="mt-4 inline-flex rounded-lg px-1.5 py-0.5 text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Clear filters
                        </a>
                    @else
                        <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
                            No users yet
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            User accounts will appear here when they are created.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
