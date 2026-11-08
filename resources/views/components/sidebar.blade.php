{{-- Global authenticated sidebar. Data-driven: future grouped navigation
     (parent item + children) is an array edit, not a structural redesign. --}}
@php
    $isActive = fn (string $pattern): bool => request()->routeIs($pattern);

    $groups = [
        [
            'items' => [
                [
                    'label' => 'Projects',
                    'route' => 'projects.index',
                    'active' => $isActive('projects.*'),
                    'children' => [], // Reserved for grouped navigation — do not populate yet.
                ],
            ],
        ],
    ];
@endphp

<aside
    id="app-sidebar"
    aria-label="Application sidebar"
    class="invisible fixed inset-y-0 left-0 z-30 flex w-64 shrink-0 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[transform,visibility] duration-200 lg:sticky lg:visible lg:bottom-auto lg:top-16 lg:z-auto lg:h-[calc(100vh-4rem)] lg:translate-x-0 lg:self-start"
>
    <nav aria-label="Main navigation" class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
            @foreach ($groups as $group)
                @foreach ($group['items'] as $item)
                    <li>
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($item['active']) aria-current="page" @endif
                            @class([
                                'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                                'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $item['active'],
                                'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' => ! $item['active'],
                            ])
                        >
                            <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            {{ $item['label'] }}
                        </a>

                        @if (! empty($item['children']))
                            {{-- Future child pages render here, indented under the parent. --}}
                            <ul class="mt-1 ml-7 space-y-1">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ route($child['route']) }}" @if ($child['active']) aria-current="page" @endif
                                            class="block rounded-lg px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                                            {{ $child['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </nav>
</aside>
