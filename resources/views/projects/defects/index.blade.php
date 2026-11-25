@extends('layouts.app')

@section('title', 'Defects | '.$project->name)

@section('content')
    @php
        $statusStyles = [
            'open' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400',
            'in_progress' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
            'resolved' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400',
            'closed' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
            'wont_fix' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400',
        ];
        $statusLabels = [
            'open' => 'Open',
            'in_progress' => 'In progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'wont_fix' => "Won't fix",
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
        <a
            href="{{ route('projects.show', $project) }}"
            class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-slate-500 transition-colors hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            {{ $project->name }}
        </a>

        <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Defects</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Defects reported against {{ $project->name }}.</p>
            </div>

            <a
                href="{{ route('projects.testing.index', $project) }}"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
                Testing workspace
            </a>
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

        @if ($canManage)
            <details class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-white">Report a defect</summary>
                <form method="POST" action="{{ route('projects.defects.store', $project) }}" class="space-y-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="new-defect-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Title</label>
                            <input id="new-defect-title" type="text" name="title" value="{{ old('title') }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </div>
                        <div>
                            <label for="new-defect-severity" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Severity</label>
                            <select id="new-defect-severity" name="severity" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                @foreach (\App\Models\Defect::SEVERITIES as $severity)
                                    <option value="{{ $severity }}" @selected(old('severity', \App\Models\Defect::SEVERITY_MAJOR) === $severity)>{{ ucfirst($severity) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="new-defect-result" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Linked result</label>
                            <select id="new-defect-result" name="test_result_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option value="">No linked result</option>
                                @foreach ($results as $result)
                                    <option value="{{ $result->id }}" @selected((string) old('test_result_id') === (string) $result->id)>
                                        {{ $result->runCase->run->displayKey() }} · {{ $result->runCase->title }} ({{ str_replace('_', ' ', $result->status) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="new-defect-description" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</label>
                            <textarea id="new-defect-description" name="description" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Report defect</button>
                </form>
            </details>
        @endif

        @if ($defects->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <x-empty-state heading="No defects yet" description="{{ $canManage ? 'Report the first defect above.' : 'No defects have been reported for this project.' }}" />
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($defects as $defect)
                    <li class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $defect->displayKey() }}</span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusStyles[$defect->status] ?? $statusStyles['open'] }}">{{ $statusLabels[$defect->status] ?? ucfirst($defect->status) }}</span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $severityStyles[$defect->severity] ?? $severityStyles['major'] }}">{{ ucfirst($defect->severity) }}</span>
                            </div>
                            <h2 class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $defect->title }}</h2>
                            @if ($defect->description)
                                <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $defect->description }}</p>
                            @endif
                            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                @if ($defect->reporter)
                                    Reported by {{ $defect->reporter->name }} ·
                                @endif
                                Updated <x-local-time :at="$defect->updated_at" />
                                @if ($defect->testResult)
                                    · Traced to
                                    <a href="{{ route('projects.test-runs.show', [$project, $defect->testResult->runCase->test_run_id]) }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                        {{ $defect->testResult->runCase->run->displayKey() }} · {{ $defect->testResult->runCase->title }}
                                    </a>
                                @endif
                            </p>
                        </div>

                        @if ($canManage)
                            <div class="flex flex-wrap items-start gap-6 px-5 py-4">
                                <details class="w-full sm:w-auto">
                                    <summary class="cursor-pointer rounded-lg text-xs font-medium text-slate-500 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200">Edit details</summary>
                                    <form method="POST" action="{{ route('projects.defects.update', [$project, $defect]) }}" class="mt-3 w-full space-y-3 sm:w-96">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label for="defect-{{ $defect->id }}-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Title</label>
                                            <input id="defect-{{ $defect->id }}-title" type="text" name="title" value="{{ $defect->title }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        </div>
                                        <div>
                                            <label for="defect-{{ $defect->id }}-severity" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Severity</label>
                                            <select id="defect-{{ $defect->id }}-severity" name="severity" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                @foreach (\App\Models\Defect::SEVERITIES as $severity)
                                                    <option value="{{ $severity }}" @selected($defect->severity === $severity)>{{ ucfirst($severity) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="defect-{{ $defect->id }}-result" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Linked result</label>
                                            <select id="defect-{{ $defect->id }}-result" name="test_result_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                <option value="">No linked result</option>
                                                @foreach ($results as $result)
                                                    <option value="{{ $result->id }}" @selected($defect->test_result_id === $result->id)>
                                                        {{ $result->runCase->run->displayKey() }} · {{ $result->runCase->title }} ({{ str_replace('_', ' ', $result->status) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="defect-{{ $defect->id }}-description" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</label>
                                            <textarea id="defect-{{ $defect->id }}-description" name="description" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $defect->description }}</textarea>
                                        </div>
                                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">Save details</button>
                                    </form>
                                </details>

                                <form method="POST" action="{{ route('projects.defects.status.update', [$project, $defect]) }}" class="flex items-end gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label for="defect-{{ $defect->id }}-status" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Status</label>
                                        <select id="defect-{{ $defect->id }}-status" name="status" class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                            @foreach (\App\Models\Defect::STATUSES as $status)
                                                <option value="{{ $status }}" @selected($defect->status === $status)>{{ $statusLabels[$status] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">Update status</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
