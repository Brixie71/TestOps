@props([
    'paginator',
    'label' => 'results',
])

@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();

    $pageWindow = $lastPage <= 7
        ? collect(range(1, $lastPage))
        : collect([1, $lastPage])
            ->merge(range(max(2, $currentPage - 1), min($lastPage - 1, $currentPage + 1)))
            ->sort()
            ->unique()
            ->values();
@endphp

@if ($paginator->hasPages())
    <nav aria-label="{{ $label }} pagination" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
            Page {{ $currentPage }} of {{ $lastPage }}
        </p>

        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex h-9 cursor-not-allowed items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-400 opacity-60 dark:border-slate-700 dark:text-slate-600">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Previous</a>
            @endif

            <div class="hidden items-center gap-1.5 md:flex">
                @foreach ($pageWindow as $page)
                    @if (! $loop->first && $page - $pageWindow[$loop->index - 1] > 1)
                        <span aria-hidden="true" class="px-1 text-sm text-slate-400 dark:text-slate-600">&hellip;</span>
                    @endif

                    @if ($page === $currentPage)
                        <span aria-current="page" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-2 text-sm font-semibold text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">{{ $page }}</a>
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Next</a>
            @else
                <span aria-disabled="true" class="inline-flex h-9 cursor-not-allowed items-center rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-400 opacity-60 dark:border-slate-700 dark:text-slate-600">Next</span>
            @endif
        </div>
    </nav>
@endif
