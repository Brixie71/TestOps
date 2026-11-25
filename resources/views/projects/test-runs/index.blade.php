@extends('layouts.app')

@section('title', 'Test runs | '.$project->name)

@section('content')
    @php
        $statusStyles = [
            'open' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
            'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400',
            'aborted' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400',
        ];
    @endphp

    <div class="mx-auto w-full max-w-6xl space-y-6">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a
                href="{{ route('projects.show', $project) }}"
                class="inline-flex items-center gap-1.5 rounded-lg font-medium text-slate-500 transition-colors hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                {{ $project->name }}
            </a>
            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
            <a
                href="{{ route('projects.testing.index', $project) }}"
                class="rounded font-medium text-slate-500 transition-colors hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200"
            >
                Testing
            </a>
        </div>

        <header>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Test runs</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Start a run over selected cases. Each run keeps a frozen copy of its cases.</p>
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

        <div class="grid gap-6 lg:grid-cols-12">
            @if ($canManage)
                <section aria-labelledby="start-run-heading" class="lg:col-span-5">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                            <h2 id="start-run-heading" class="text-base font-semibold text-slate-900 dark:text-white">Start a run</h2>
                        </div>

                        <form method="POST" action="{{ route('projects.test-runs.store', $project) }}" class="space-y-5 px-5 py-4">
                            @csrf

                            <div>
                                <label for="run-name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Run name</label>
                                <input
                                    id="run-name"
                                    name="name"
                                    type="text"
                                    required
                                    maxlength="255"
                                    value="{{ old('name') }}"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                >
                            </div>

                            @if ($suites->isEmpty())
                                <p class="text-sm text-slate-500 dark:text-slate-400">Create a suite and at least one case before starting a run.</p>
                            @else
                                <fieldset class="space-y-4">
                                    <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Cases</legend>

                                    @foreach ($suites as $suite)
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $suite->name }}</p>

                                            @forelse ($suite->testCases as $case)
                                                <label class="mt-1.5 flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                                                    <input
                                                        type="checkbox"
                                                        name="case_ids[]"
                                                        value="{{ $case->id }}"
                                                        @checked(in_array($case->id, (array) old('case_ids', []), true))
                                                        class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950"
                                                    >
                                                    <span><span class="font-medium text-slate-900 dark:text-white">{{ $case->displayKey() }}</span> {{ $case->title }}</span>
                                                </label>
                                            @empty
                                                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">No cases in this suite.</p>
                                            @endforelse
                                        </div>
                                    @endforeach
                                </fieldset>

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                                >
                                    Start run
                                </button>
                            @endif
                        </form>
                    </div>
                </section>
            @endif

            <section aria-labelledby="runs-heading" class="{{ $canManage ? 'lg:col-span-7' : 'lg:col-span-12' }}">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 id="runs-heading" class="text-base font-semibold text-slate-900 dark:text-white">Runs</h2>
                        <span class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ $runs->count() }}</span>
                    </div>

                    @if ($runs->isEmpty())
                        <x-empty-state heading="No runs yet" description="Start a run to snapshot the selected cases." />
                    @else
                        <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach ($runs as $run)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                    <div class="min-w-0">
                                        <a
                                            href="{{ route('projects.test-runs.show', [$project, $run]) }}"
                                            class="rounded text-sm font-semibold text-slate-900 transition-colors hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-white dark:hover:text-indigo-400"
                                        >
                                            <span class="text-slate-500 dark:text-slate-400">{{ $run->displayKey() }}</span> {{ $run->name }}
                                        </a>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                            {{ $run->cases_count }} {{ Str::plural('case', $run->cases_count) }}
                                            &middot;
                                            Started <x-local-time :at="$run->started_at" fallback="—" />
                                        </p>
                                    </div>

                                    <span class="shrink-0 rounded-md border px-2 py-0.5 text-xs font-medium {{ $statusStyles[$run->status] ?? $statusStyles['open'] }}">
                                        {{ ucfirst($run->status) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
