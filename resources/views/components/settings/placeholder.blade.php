@props([
    'title',
    'description',
])

<section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-start gap-4">
        <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </span>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    Not yet implemented
                </span>
            </div>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
        </div>
    </div>
</section>
