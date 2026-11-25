@extends('layouts.app')

@section('title', 'Testing | '.$project->name)

@section('content')
    @php
        $statusStyles = [
            'draft' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
            'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400',
            'deprecated' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400',
        ];
        $severityStyles = [
            'blocker' => 'border-red-300 bg-red-100 text-red-800 dark:border-red-900/70 dark:bg-red-500/15 dark:text-red-300',
            'critical' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400',
            'major' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/70 dark:bg-orange-500/10 dark:text-orange-400',
            'minor' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-500/10 dark:text-sky-400',
            'trivial' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
        ];
        $stepRows = 5;
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
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Testing</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Suites and cases for {{ $project->name }}.</p>
            </div>
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
            {{-- Suites --}}
            <section aria-labelledby="suites-heading" class="lg:col-span-4">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 id="suites-heading" class="text-base font-semibold text-slate-900 dark:text-white">Suites</h2>
                        <span class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ $suites->count() }}</span>
                    </div>

                    @if ($suites->isEmpty())
                        <x-empty-state heading="No suites yet" description="Create a suite to start grouping test cases." />
                    @else
                        <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach ($suites as $suite)
                                @php($isSelected = $selectedSuite && $selectedSuite->id === $suite->id)
                                <li class="px-5 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <a
                                            href="{{ route('projects.testing.index', ['project' => $project, 'suite' => $suite->id]) }}"
                                            @class([
                                                'min-w-0 rounded text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                                'text-indigo-600 dark:text-indigo-400' => $isSelected,
                                                'text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400' => ! $isSelected,
                                            ])
                                        >
                                            {{ $suite->name }}
                                        </a>
                                        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            {{ $suite->test_cases_count }} {{ Str::plural('case', $suite->test_cases_count) }}
                                        </span>
                                    </div>

                                    @if ($suite->description)
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $suite->description }}</p>
                                    @endif

                                    @if ($canManage)
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <details class="group">
                                                <summary class="cursor-pointer rounded-lg text-xs font-medium text-slate-500 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200">Edit</summary>
                                                <form method="POST" action="{{ route('projects.test-suites.update', [$project, $suite]) }}" class="mt-2 space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="text" name="name" value="{{ $suite->name }}" required maxlength="255" aria-label="Suite name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                    <textarea name="description" rows="2" maxlength="2000" aria-label="Suite description" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $suite->description }}</textarea>
                                                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">Save suite</button>
                                                </form>
                                            </details>

                                            @if ($suite->test_cases_count === 0)
                                                <form method="POST" action="{{ route('projects.test-suites.destroy', [$project, $suite]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg text-xs font-medium text-red-600 hover:text-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-red-400">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($canManage)
                    <details class="mt-4 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-white">New suite</summary>
                        <form method="POST" action="{{ route('projects.test-suites.store', $project) }}" class="space-y-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                            @csrf
                            <div>
                                <label for="new-suite-name" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Name</label>
                                <input id="new-suite-name" type="text" name="name" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                            <div>
                                <label for="new-suite-description" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</label>
                                <textarea id="new-suite-description" name="description" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Create suite</button>
                        </form>
                    </details>
                @endif
            </section>

            {{-- Cases --}}
            <section aria-labelledby="cases-heading" class="space-y-4 lg:col-span-8">
                @if ($selectedSuite === null)
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <x-empty-state heading="No suite selected" description="{{ $canManage ? 'Create a suite first, then add cases to it.' : 'This project has no test suites yet.' }}" />
                    </div>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="cases-heading" class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ $selectedSuite->name }}
                            <span class="ml-1 text-sm font-normal text-slate-400 dark:text-slate-500">{{ $cases->count() }} {{ Str::plural('case', $cases->count()) }}</span>
                        </h2>
                    </div>

                    @if ($cases->isEmpty())
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <x-empty-state heading="No cases in this suite" description="{{ $canManage ? 'Add the first case below.' : 'No cases have been authored yet.' }}" />
                        </div>
                    @else
                        <ul class="space-y-4">
                            @foreach ($cases as $case)
                                <li class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $case->displayKey() }}</span>
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusStyles[$case->status] ?? $statusStyles['draft'] }}">{{ ucfirst($case->status) }}</span>
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $severityStyles[$case->severity] ?? $severityStyles['major'] }}">{{ ucfirst($case->severity) }}</span>
                                        </div>
                                        <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $case->title }}</h3>
                                        @if ($case->description)
                                            <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $case->description }}</p>
                                        @endif
                                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                            @if ($case->author)
                                                By {{ $case->author->name }} ·
                                            @endif
                                            Updated <x-local-time :at="$case->updated_at" />
                                        </p>
                                    </div>

                                    <div class="px-5 py-4">
                                        @if ($case->steps->isEmpty())
                                            <p class="text-sm text-slate-400 dark:text-slate-500">No steps recorded.</p>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-left text-sm">
                                                    <thead>
                                                        <tr class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                                            <th scope="col" class="w-10 py-1 font-semibold">#</th>
                                                            <th scope="col" class="py-1 font-semibold">Action</th>
                                                            <th scope="col" class="py-1 font-semibold">Expected result</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                        @foreach ($case->steps as $step)
                                                            <tr>
                                                                <td class="py-2 pr-3 align-top font-mono text-xs text-slate-400 dark:text-slate-500">{{ $step->position }}</td>
                                                                <td class="py-2 pr-3 align-top text-slate-700 dark:text-slate-300">{{ $step->action }}</td>
                                                                <td class="py-2 align-top text-slate-500 dark:text-slate-400">{{ $step->expected_result ?? '—' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($canManage)
                                        <div class="flex flex-wrap items-center gap-4 border-t border-slate-200 px-5 py-3 dark:border-slate-800">
                                            <details class="group w-full">
                                                <summary class="cursor-pointer rounded-lg text-xs font-medium text-slate-500 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200">Edit case</summary>
                                                <form method="POST" action="{{ route('projects.test-cases.update', [$project, $case]) }}" class="mt-3 space-y-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="grid gap-3 sm:grid-cols-2">
                                                        <div class="sm:col-span-2">
                                                            <label for="case-{{ $case->id }}-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Title</label>
                                                            <input id="case-{{ $case->id }}-title" type="text" name="title" value="{{ $case->title }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label for="case-{{ $case->id }}-status" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Status</label>
                                                            <select id="case-{{ $case->id }}-status" name="status" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                                @foreach (\App\Models\TestCase::STATUSES as $status)
                                                                    <option value="{{ $status }}" @selected($case->status === $status)>{{ ucfirst($status) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label for="case-{{ $case->id }}-severity" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Severity</label>
                                                            <select id="case-{{ $case->id }}-severity" name="severity" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                                @foreach (\App\Models\TestCase::SEVERITIES as $severity)
                                                                    <option value="{{ $severity }}" @selected($case->severity === $severity)>{{ ucfirst($severity) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label for="case-{{ $case->id }}-description" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</label>
                                                            <textarea id="case-{{ $case->id }}-description" name="description" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $case->description }}</textarea>
                                                        </div>
                                                    </div>

                                                    @include('projects.testing._steps', [
                                                        'prefix' => 'case-'.$case->id,
                                                        'steps' => $case->steps,
                                                        'rows' => $stepRows,
                                                    ])

                                                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">Save case</button>
                                                </form>
                                            </details>

                                            <form method="POST" action="{{ route('projects.test-cases.destroy', [$project, $case]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg text-xs font-medium text-red-600 hover:text-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-red-400">Delete case</button>
                                            </form>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($canManage)
                        <details class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-white">New case in {{ $selectedSuite->name }}</summary>
                            <form method="POST" action="{{ route('projects.test-cases.store', $project) }}" class="space-y-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                                @csrf
                                <input type="hidden" name="test_suite_id" value="{{ $selectedSuite->id }}">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label for="new-case-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Title</label>
                                        <input id="new-case-title" type="text" name="title" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    </div>
                                    <div>
                                        <label for="new-case-status" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Status</label>
                                        <select id="new-case-status" name="status" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                            @foreach (\App\Models\TestCase::STATUSES as $status)
                                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="new-case-severity" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Severity</label>
                                        <select id="new-case-severity" name="severity" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                            @foreach (\App\Models\TestCase::SEVERITIES as $severity)
                                                <option value="{{ $severity }}" @selected($severity === \App\Models\TestCase::SEVERITY_MAJOR)>{{ ucfirst($severity) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="new-case-description" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</label>
                                        <textarea id="new-case-description" name="description" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                                    </div>
                                </div>

                                @include('projects.testing._steps', [
                                    'prefix' => 'new-case',
                                    'steps' => collect(),
                                    'rows' => $stepRows,
                                ])

                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Create case</button>
                            </form>
                        </details>
                    @endif
                @endif
            </section>
        </div>
    </div>
@endsection
