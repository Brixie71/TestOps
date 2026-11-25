@extends('layouts.app')

@section('title', $run->displayKey().' | '.$project->name)

@section('content')
    @php
        $statusStyles = [
            'open' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
            'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400',
            'aborted' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400',
        ];
        $severityStyles = [
            'blocker' => 'border-red-300 bg-red-100 text-red-800 dark:border-red-900/70 dark:bg-red-500/15 dark:text-red-300',
            'critical' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400',
            'major' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/70 dark:bg-orange-500/10 dark:text-orange-400',
            'minor' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
            'trivial' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
        ];
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-6">
        <a
            href="{{ route('projects.test-runs.index', $project) }}"
            class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-slate-500 transition-colors hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Test runs
        </a>

        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $run->displayKey() }}</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $run->name }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Started <x-local-time :at="$run->started_at" fallback="—" />
                    by {{ $run->author?->name ?? 'a deleted account' }}
                    @if ($run->completed_at)
                        &middot; Finished <x-local-time :at="$run->completed_at" fallback="—" />
                    @endif
                </p>
            </div>

            <span class="shrink-0 rounded-md border px-2.5 py-1 text-xs font-medium {{ $statusStyles[$run->status] ?? $statusStyles['open'] }}">
                {{ ucfirst($run->status) }}
            </span>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($run->isOpen() && $canManage)
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('projects.test-runs.complete', [$project, $run]) }}">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    >
                        Complete run
                    </button>
                </form>

                <form method="POST" action="{{ route('projects.test-runs.abort', [$project, $run]) }}">
                    @csrf
                    @method('PATCH')
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Abort run
                    </button>
                </form>
            </div>
        @endif

        <section aria-labelledby="run-cases-heading" class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 id="run-cases-heading" class="text-base font-semibold text-slate-900 dark:text-white">Frozen cases</h2>
                <span class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ $run->cases->count() }}</span>
            </div>

            @foreach ($run->cases as $runCase)
                <article class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">#{{ $runCase->position }}</p>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $runCase->title }}</h3>
                        </div>

                        <span class="shrink-0 rounded-md border px-2 py-0.5 text-xs font-medium {{ $severityStyles[$runCase->severity] ?? $severityStyles['major'] }}">
                            {{ ucfirst($runCase->severity) }}
                        </span>
                    </div>

                    @if (empty($runCase->steps))
                        <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">No steps were recorded for this case.</p>
                    @else
                        <ol class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach ($runCase->steps as $step)
                                <li class="flex gap-3 px-5 py-3 text-sm">
                                    <span class="shrink-0 font-medium text-slate-400 dark:text-slate-500">{{ $loop->iteration }}.</span>
                                    <div class="min-w-0 space-y-1">
                                        <p class="text-slate-800 dark:text-slate-200">{{ $step['action'] }}</p>
                                        @if (! empty($step['expected_result']))
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Expected: {{ $step['expected_result'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </article>
            @endforeach
        </section>
    </div>
@endsection
