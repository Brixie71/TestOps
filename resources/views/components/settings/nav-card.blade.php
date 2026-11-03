@props([
    'href',
    'title',
    'description',
])

<a
    href="{{ $href }}"
    class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-indigo-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/50"
>
    <div class="flex items-start justify-between gap-3">
        <span aria-hidden="true" class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
            {{ $slot }}
        </span>
        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
            Not yet implemented
        </span>
    </div>
    <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
    <span class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400">
        Open
        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
        </svg>
    </span>
</a>
