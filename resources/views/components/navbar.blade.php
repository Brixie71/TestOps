{{-- Global authenticated top navigation bar.
     Left: mobile sidebar toggle + branding. Right: [existing theme toggle] + user menu. --}}

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-2">
            {{-- Mobile sidebar toggle (unchanged — required by layouts/app drawer script) --}}
            <button
                type="button"
                id="sidebar-toggle"
                aria-controls="app-sidebar"
                aria-expanded="false"
                aria-label="Open navigation menu"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 lg:hidden"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            {{-- Application branding (unchanged) --}}
            <div class="flex items-center gap-2.5">
                <svg class="h-8 w-8 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="testops-mark-navbar" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#6366F1" />
                            <stop offset="1" stop-color="#4F46E5" />
                        </linearGradient>
                    </defs>
                    <rect width="40" height="40" rx="10" fill="url(#testops-mark-navbar)" />
                    <path d="M12 20.75L17.25 26L28 14.75" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <p class="text-base font-bold tracking-tight text-slate-900 dark:text-white">
                    Test<span class="text-indigo-600 dark:text-indigo-400">Ops</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            {{-- >>> PRESERVE: if your build has an existing theme toggle button, keep it here. <<< --}}

            @php
                $user = auth()->user();
                $userInitial = mb_strtoupper(mb_substr(trim((string) $user?->name), 0, 1)) ?: 'U';
            @endphp

            <div class="relative">
                <button
                    type="button"
                    id="user-menu-trigger"
                    aria-controls="user-menu"
                    aria-expanded="false"
                    class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white py-1.5 pl-1.5 pr-2.5 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <span aria-hidden="true" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">
                        {{ $userInitial }}
                    </span>
                    <span class="max-w-[10rem] truncate">{{ $user?->name }}</span>
                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <div
                    id="user-menu"
                    class="absolute right-0 z-50 mt-2 hidden w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg shadow-slate-900/5 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/30"
                >
                    <a
                        href="{{ route('profile') }}"
                        class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:bg-slate-800"
                    >
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Profile
                    </a>
                    <a
                        href="{{ route('settings.index') }}"
                        class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:bg-slate-800"
                    >
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>

                    <div class="my-1.5 border-t border-slate-200 dark:border-slate-700"></div>

                    {{-- Existing POST logout flow with CSRF — unchanged behavior --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:text-red-400 dark:hover:bg-red-500/10 dark:focus:bg-red-500/10"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-7.5A2.25 2.25 0 003.75 5.25v13.5A2.25 2.25 0 006 21h7.5a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    (function () {
        var trigger = document.getElementById('user-menu-trigger');
        var menu = document.getElementById('user-menu');

        if (!trigger || !menu) {
            return;
        }

        function isOpen() {
            return !menu.classList.contains('hidden');
        }

        function openMenu() {
            menu.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
        }

        function closeMenu(returnFocus) {
            menu.classList.add('hidden');
            trigger.setAttribute('aria-expanded', 'false');

            if (returnFocus) {
                trigger.focus();
            }
        }

        trigger.addEventListener('click', function () {
            if (isOpen()) {
                closeMenu(false);
            } else {
                openMenu();
            }
        });

        document.addEventListener('click', function (event) {
            if (isOpen() && !trigger.contains(event.target) && !menu.contains(event.target)) {
                closeMenu(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) {
                closeMenu(true);
            }
        });
    })();
</script>
