{{-- Fixed ordered step rows: blank rows are ignored on submit. --}}
@php
    $existing = collect($steps)->values();
@endphp

<fieldset class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
    <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Steps</legend>
    <div class="space-y-2">
        @for ($i = 0; $i < $rows; $i++)
            @php($step = $existing->get($i))
            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    <label for="{{ $prefix }}-step-{{ $i }}-action" class="sr-only">Step {{ $i + 1 }} action</label>
                    <input
                        id="{{ $prefix }}-step-{{ $i }}-action"
                        type="text"
                        name="steps[{{ $i }}][action]"
                        value="{{ $step->action ?? '' }}"
                        maxlength="1000"
                        placeholder="Action {{ $i + 1 }}"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-600"
                    >
                </div>
                <div>
                    <label for="{{ $prefix }}-step-{{ $i }}-expected" class="sr-only">Step {{ $i + 1 }} expected result</label>
                    <input
                        id="{{ $prefix }}-step-{{ $i }}-expected"
                        type="text"
                        name="steps[{{ $i }}][expected_result]"
                        value="{{ $step->expected_result ?? '' }}"
                        maxlength="1000"
                        placeholder="Expected result {{ $i + 1 }}"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-600"
                    >
                </div>
            </div>
        @endfor
    </div>
</fieldset>
