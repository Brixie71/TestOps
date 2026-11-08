@props([
    'id',
    'title',
    'description' => null,
    'context' => null, // Hidden form field value used to reopen this dialog after validation errors.
])

@php
    $reopen = $context !== null && $errors->any() && old('dialog') === $context;
@endphp

<div
    {{ $attributes->merge(['id' => $id]) }}
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    @if ($description) aria-describedby="{{ $id }}-description" @endif
    @class([
        'fixed inset-0 z-50',
        'hidden' => ! $reopen,
    ])
>
    <div data-dialog-close class="absolute inset-0 bg-slate-950/60" aria-hidden="true"></div>

    <div class="absolute inset-0 flex items-end justify-center p-4 sm:items-center">
        <div class="max-h-full w-full max-w-lg overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div>
                    <h2 id="{{ $id }}-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    @if ($description)
                        <p id="{{ $id }}-description" class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" data-dialog-close aria-label="Close dialog" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:bg-slate-800 dark:hover:text-slate-300">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-5 py-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
