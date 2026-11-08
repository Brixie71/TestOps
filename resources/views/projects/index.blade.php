@extends('layouts.app')

@section('title', 'Projects | TestOps')

@section('content')
    @php
        // Explicit guards: PHP treats the string '0' as falsy (regression lesson
        // from the User Directory). Pagination preserves filters via withQueryString().
        $hasFilters = $search !== null && $search !== '';
    @endphp

    <section aria-labelledby="projects-heading" class="space-y-6">
        <header>
            <h1 id="projects-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Projects</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Browse the projects you have access to and open one to view its details, members, and ownership.
            </p>
        </header>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800 sm:p-5">
                <form method="GET" action="{{ route('projects.index') }}" class="flex flex-col gap-3 sm:flex-row">
                    <div class="min-w-0 flex-1">
                        <label for="project-search" class="sr-only">Search projects by name or code</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path stroke-linecap="round" d="m16 16 4 4" />
                            </svg>
                            <input
                                id="project-search"
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Search by name or code..."
                                class="block w-full rounded-lg border border-slate-200 bg-white py-2.5 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        Search
                    </button>

                    @if ($hasFilters)
                        <a
                            href="{{ route('projects.index') }}"
                            class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        >
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Project directory</p>
                <p class="text-xs text-slate-500 dark:text-slate-400" aria-live="polite">
                    Showing {{ $projects->firstItem() ?? 0 }}&ndash;{{ $projects->lastItem() ?? 0 }}
                    of {{ $projects->total() }} {{ Str::plural('project', $projects->total()) }}
                </p>
            </div>

            @if ($projects->isNotEmpty())
                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <caption class="sr-only">Projects you have access to</caption>
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-5 py-3">Project</th>
                                <th scope="col" class="px-5 py-3">Description</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3">Administrator</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($projects as $project)
                                @php
                                    $owner = $project->owner;
                                    $ownerDeactivated = $owner && method_exists($owner, 'trashed') && $owner->trashed();
                                    $initial = $owner ? (mb_strtoupper(mb_substr(trim((string) $owner->name), 0, 1)) ?: 'U') : null;
                                @endphp
                                <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col items-start gap-1.5">
                                            <a
                                                href="{{ route('projects.show', $project) }}"
                                                class="rounded font-medium text-slate-900 transition-colors hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-100 dark:hover:text-indigo-400"
                                            >
                                                {{ $project->name }}
                                            </a>
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                {{ $project->code }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="max-w-xs px-5 py-4">
                                        <p class="line-clamp-2 text-slate-600 dark:text-slate-400">
                                            {{ $project->description ?? '—' }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <x-status-badge :status="$project->status" />
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($owner)
                                            <div class="flex items-center gap-2.5">
                                                <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">{{ $initial }}</span>
                                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $owner->name }}</span>
                                            </div>
                                            <div class="mt-1.5">
                                                @if ($ownerDeactivated)
                                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400">
                                                        Deactivated
                                                    </span>
                                                @else
                                                    <x-projects.ownership-badge :state="$project->ownership_type" />
                                                @endif
                                            </div>
                                        @else
                                            <x-projects.ownership-badge state="none" />
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a
                                            href="{{ route('projects.show', $project) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                            aria-label="Open {{ $project->name }} details"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800">
                    <x-pagination :paginator="$projects" label="projects" />
                </div>
            @else
                <div class="border-t border-slate-200 dark:border-slate-800">
                    @if ($hasFilters)
                        <x-empty-state heading="No matching projects" description="No projects match the current search.">
                            <x-slot:icon>
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7" />
                                    <path stroke-linecap="round" d="m16 16 4 4" />
                                </svg>
                            </x-slot:icon>
                            <x-slot:action>
                                <a href="{{ route('projects.index') }}" class="rounded-lg px-1.5 py-0.5 text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    Clear search
                                </a>
                            </x-slot:action>
                        </x-empty-state>
                    @else
                        <x-empty-state heading="No projects yet" description="Projects will appear here once they are created or shared with you.">
                            <x-slot:icon>
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                </svg>
                            </x-slot:icon>
                        </x-empty-state>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
