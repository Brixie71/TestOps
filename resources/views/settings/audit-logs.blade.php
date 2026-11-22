@extends('layouts.settings')

@section('title', 'Audit Logs | TestOps')

@section('settings-content')
    <section aria-labelledby="audit-logs-heading" class="space-y-6">
        <header>
            <h2 id="audit-logs-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Audit Logs
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Review the administrative history of TestOps: lifecycle, role, project, and account events.
            </p>
        </header>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {{-- Filters (GET) per §12.5: actor, target user, action type, date range --}}
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <form method="GET" action="{{ route('settings.audit-logs') }}" class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="audit-actor" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Actor
                            </label>
                            <input
                                id="audit-actor"
                                type="text"
                                name="actor"
                                value="{{ $filters['actor'] ?? '' }}"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Name or email"
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            >
                        </div>

                        <div>
                            <label for="audit-target" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Target user
                            </label>
                            <input
                                id="audit-target"
                                type="text"
                                name="target"
                                value="{{ $filters['target'] ?? '' }}"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Name or email"
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <x-settings.filter-select name="action" label="Filter by action type" class="w-56">
                            <option value="">All actions</option>
                            @foreach ($actions as $action)
                                <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                                    {{ $action }}
                                </option>
                            @endforeach
                        </x-settings.filter-select>

                        <div>
                            <label for="audit-date-from" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                From
                            </label>
                            <input
                                id="audit-date-from"
                                type="date"
                                name="date_from"
                                value="{{ $filters['date_from'] ?? '' }}"
                                class="mt-1 block w-40 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:[color-scheme:dark]"
                            >
                        </div>

                        <div>
                            <label for="audit-date-to" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                To
                            </label>
                            <input
                                id="audit-date-to"
                                type="date"
                                name="date_to"
                                value="{{ $filters['date_to'] ?? '' }}"
                                class="mt-1 block w-40 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:[color-scheme:dark]"
                            >
                        </div>

                        <div class="flex items-center gap-2 sm:ml-auto">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white">
                                Apply filters
                            </button>

                            @if ($hasFilters)
                                <a href="{{ route('settings.audit-logs') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            {{-- Results summary + export using the same active filters (§12.5) --}}
            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Audit history</p>
                <div class="flex items-center gap-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400" aria-live="polite">
                        Showing {{ $datePaginator->firstItem() ?? 0 }}&ndash;{{ $datePaginator->lastItem() ?? 0 }}
                        of {{ $datePaginator->total() }} {{ Str::plural('day', $datePaginator->total()) }}
                        &middot; {{ $totalEvents }} {{ Str::plural('event', $totalEvents) }}
                    </p>

                    <a
                        href="{{ route('settings.audit-logs.export', request()->query()) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16" />
                        </svg>
                        Export CSV
                    </a>

                    <a
                        href="{{ route('settings.audit-logs.export.pdf', request()->query()) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16" />
                        </svg>
                        Export PDF
                    </a>
                </div>
            </div>

            @if ($dayGroups->isNotEmpty())
                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                    <table class="w-full min-w-[880px] text-left text-sm">
                        <caption class="sr-only">TestOps audit history grouped by date</caption>
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Time</th>
                                <th scope="col" class="px-4 py-3">Action</th>
                                <th scope="col" class="px-4 py-3">Actor</th>
                                <th scope="col" class="px-4 py-3">Target</th>
                                <th scope="col" class="px-4 py-3">Reason</th>
                            </tr>
                        </thead>
                        @foreach ($dayGroups as $day)
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr class="bg-slate-50/80 dark:bg-slate-950/40">
                                    <th colspan="5" scope="colgroup" class="px-4 py-2.5 text-left text-xs font-semibold text-slate-600 dark:text-slate-300">
                                        <time datetime="{{ $day['date']->toDateString() }}">
                                            {{ $day['date']->format('M d, Y') }}
                                        </time>
                                        <span class="ml-2 font-normal text-slate-400 dark:text-slate-500">
                                            {{ $day['count'] }} {{ Str::plural('event', $day['count']) }}
                                        </span>
                                    </th>
                                </tr>
                                @foreach ($day['groups'] as $group)
                                    <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-4">
                                            <div class="flex max-w-[220px] flex-wrap gap-1">
                                                @foreach ($group['times'] as $time)
                                                    <time datetime="{{ $time->toIso8601String() }}" class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ $time->format('H:i') }}
                                                    </time>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                {{ $group['action'] }}
                                            </span>
                                            @if ($group['count'] > 1)
                                                <span
                                                    class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                                                    title="Occurrences of this action on this date"
                                                >
                                                    &times;{{ $group['count'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            @if ($group['actor_name'] || $group['actor_email'])
                                                <span class="text-slate-700 dark:text-slate-300">{{ $group['actor_name'] ?: '—' }}</span>
                                                <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $group['actor_email'] }}</span>
                                            @else
                                                <span class="text-slate-400 dark:text-slate-500">system</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            @if ($group['target_name'] || $group['target_email'])
                                                <span class="text-slate-700 dark:text-slate-300">{{ $group['target_name'] ?: '—' }}</span>
                                                <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $group['target_email'] }}</span>
                                            @else
                                                <span class="text-slate-400 dark:text-slate-500">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-400">
                                            {{ $group['reason'] ?: '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>

                @if ($datePaginator->hasPages())
                    <nav aria-label="Audit log pagination" class="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Page {{ $currentPage }} of {{ $lastPage }}
                        </p>

                        <div class="flex items-center gap-1.5">
                            @if ($datePaginator->onFirstPage())
                                <span aria-disabled="true" class="inline-flex h-9 cursor-not-allowed items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-400 opacity-60 dark:border-slate-700 dark:text-slate-600">
                                    Previous
                                </span>
                            @else
                                <a href="{{ $datePaginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                    Previous
                                </a>
                            @endif

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
                                        <a href="{{ $datePaginator->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                            {{ $page }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>

                            @if ($datePaginator->hasMorePages())
                                <a href="{{ $datePaginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
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
                    @if ($hasFilters)
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                            No matching events
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            No audit events match the current filters.
                        </p>
                        <a
                            href="{{ route('settings.audit-logs') }}"
                            class="mt-4 inline-flex rounded-lg px-1.5 py-0.5 text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Clear filters
                        </a>
                    @else
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                            No audit events yet
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Audit events will appear here as administrative actions are performed.
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
