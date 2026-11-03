@php
    $isActive = fn (string $route): bool => request()->routeIs($route);

    // Data-driven so future role-aware navigation (§8) only changes this array.
    $groups = [
        [
            'items' => [
                ['label' => 'General', 'route' => 'settings.index', 'active' => $isActive('settings.index')],
            ],
        ],
        [
            'label' => 'User Management',
            'items' => [
                ['label' => 'Users', 'route' => 'settings.users', 'active' => $isActive('settings.users')],
            ],
        ],
        [
            'label' => 'User Control',
            'items' => [
                ['label' => 'Roles', 'route' => 'settings.roles', 'active' => $isActive('settings.roles')],
                ['label' => 'Permissions', 'route' => 'settings.permissions', 'active' => $isActive('settings.permissions')],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                ['label' => 'About', 'route' => 'settings.about', 'active' => $isActive('settings.about')],
            ],
        ],
    ];
@endphp

<nav aria-label="Settings">
    <ul class="flex gap-1.5 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
        @foreach ($groups as $group)
            @if (isset($group['label']))
                <li class="hidden shrink-0 px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 lg:block dark:text-slate-500">
                    {{ $group['label'] }}
                </li>
            @endif
            @foreach ($group['items'] as $item)
                <li class="shrink-0">
                    <a
                        href="{{ route($item['route']) }}"
                        @if ($item['active']) aria-current="page" @endif
                        @class([
                            'block rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500',
                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $item['active'],
                            'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' => ! $item['active'],
                        ])
                    >
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        @endforeach
    </ul>
</nav>
