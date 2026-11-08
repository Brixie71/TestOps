{{-- Custom-styled filter select: OS chrome removed (appearance-none) and
     replaced with the app chevron. Pass the column width via class, e.g.
     class="w-36". The dark:[color-scheme:dark] utility makes the native
     popup list render dark in dark mode. --}}
@props(['name', 'label'])

<div {{ $attributes->merge(['class' => 'relative']) }}>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        class="block w-full cursor-pointer appearance-none rounded-lg border border-slate-200 bg-white py-2.5 pl-3 pr-9 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:[color-scheme:dark]"
    >
        {{ $slot }}
    </select>
    <svg class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
</div>
