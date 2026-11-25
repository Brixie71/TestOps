@extends('layouts.app')

@section('title', 'Dashboard | TestOps')

@section('content')
    @php
        $userId = auth()->id();

        $statusStyles = [
            'open' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400',
            'in_progress' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
        ];
        $statusLabels = [
            'open' => 'Open',
            'in_progress' => 'In progress',
        ];
        $severityStyles = [
            'blocker' => 'border-red-300 bg-red-100 text-red-800 dark:border-red-900/70 dark:bg-red-500/15 dark:text-red-300',
            'critical' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400',
            'major' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/70 dark:bg-orange-500/10 dark:text-orange-400',
            'minor' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
            'trivial' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
        ];
    @endphp

    <div class="mx-auto w-full max-w-6xl space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A read-only summary of the work you can access.</p>
        </header>

        <section aria-label="Summary metrics" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($metrics as $metric)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ number_format($metric['value']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $metric['description'] }}</p>
                </div>
            @endforeach
        </section>

        <section aria-labelledby="dashboard-projects-heading" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <div>
                    <h2 id="dashboard-projects-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Your projects</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Projects you can access, most attention needed first.</p>
                </div>
                <a
                    href="{{ route('projects.index') }}"
                    class="inline-flex items-center gap-1 rounded-lg text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    All projects
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>

            @if ($projects->isNotEmpty())
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($projects as $project)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        href="{{ route('projects.show', $project) }}"
                                        class="rounded font-medium text-slate-900 transition-colors hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-100 dark:hover:text-indigo-400"
                                    >
                                        {{ $project->name }}
                                    </a>
                                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $project->code }}</span>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                        {{ $project->owner_id === $userId ? 'Owner' : 'Member' }}
                                    </span>
                                </div>
                            </div>

                            <dl class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                @if (in_array($project->id, $runScopedProjectIds, true))
                                    <div class="flex items-baseline gap-1">
                                        <dt>Runs</dt>
                                        <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($project->open_runs_count) }} open</dd>
                                    </div>
                                @endif
                                @if (in_array($project->id, $defectScopedProjectIds, true))
                                    <div class="flex items-baseline gap-1">
                                        <dt>Defects</dt>
                                        <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ number_format($project->unresolved_defects_count) }} unresolved</dd>
                                    </div>
                                @endif
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state heading="No projects yet" description="Projects will appear here once they are created or shared with you.">
                    <x-slot:icon>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                    </x-slot:icon>
                    <x-slot:action>
                        <a href="{{ route('projects.index') }}" class="rounded-lg px-1.5 py-0.5 text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                            Browse the projects directory
                        </a>
                    </x-slot:action>
                </x-empty-state>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            @if ($showRuns)
                <section aria-labelledby="dashboard-runs-heading" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 id="dashboard-runs-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Runs in progress</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Open runs across your projects, newest first.</p>
                    </div>

                    @if ($openRuns->isNotEmpty())
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($openRuns as $run)
                                <li class="px-5 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $run->displayKey() }}</span>
                                                <a
                                                    href="{{ route('projects.test-runs.show', [$run->project_id, $run->id]) }}"
                                                    class="truncate rounded font-medium text-slate-900 transition-colors hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-100 dark:hover:text-indigo-400"
                                                >
                                                    {{ $run->name }}
                                                </a>
                                            </div>
                                            <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                                {{ $run->project_name }}
                                                @if ($run->author_name)
                                                    <span aria-hidden="true">&middot;</span> {{ $run->author_name }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ number_format($run->recorded_count) }} / {{ number_format($run->cases_count) }}</p>
                                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                <x-local-time :at="$run->started_at" format="M d, H:i" />
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No runs in progress.</p>
                    @endif
                </section>
            @endif

            @if ($showDefects)
                <section aria-labelledby="dashboard-defects-heading" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 id="dashboard-defects-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Open defects</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Open or in-progress defects, most severe first.</p>
                    </div>

                    @if ($openDefects->isNotEmpty())
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($openDefects as $defect)
                                <li class="px-5 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $defect->displayKey() }}</span>
                                                <a
                                                    href="{{ route('projects.defects.index', $defect->project_id) }}"
                                                    class="truncate rounded font-medium text-slate-900 transition-colors hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-100 dark:hover:text-indigo-400"
                                                >
                                                    {{ $defect->title }}
                                                </a>
                                            </div>
                                            <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $defect->project_name }}</p>
                                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $severityStyles[$defect->severity] ?? $severityStyles['major'] }}">
                                                    {{ ucfirst($defect->severity) }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $statusStyles[$defect->status] ?? $statusStyles['open'] }}">
                                                    {{ $statusLabels[$defect->status] ?? ucfirst($defect->status) }}
                                                </span>
                                            </div>
                                        </div>
                                        <p class="shrink-0 text-[11px] text-slate-500 dark:text-slate-400">
                                            <x-local-time :at="$defect->created_at" format="M d, H:i" />
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No open defects.</p>
                    @endif
                </section>
            @endif
        </div>
    </div>
@endsection
