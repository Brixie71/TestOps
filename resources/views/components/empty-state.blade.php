@props([
    'heading',
    'description' => null,
])

<div class="px-5 py-14 text-center">
    @isset($icon)
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
            {{ $icon }}
        </span>
    @endisset

    <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ $heading }}</h3>

    @if ($description)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif

    @if (isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
