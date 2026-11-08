@props(['status'])

@php
    // ADAPT (item 6): map real status values; unknown values fall back to neutral.
    $styles = match ((string) $status) {
        'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400',
        'archived' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
        default => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
    };

    $dots = match ((string) $status) {
        'active' => 'bg-emerald-500',
        default => 'bg-slate-400 dark:bg-slate-500',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $styles }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dots }}" aria-hidden="true"></span>
    {{ ucfirst((string) $status) ?: 'Unknown' }}
</span>
