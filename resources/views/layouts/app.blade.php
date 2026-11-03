<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'TestOps')</title>

    {{-- Apply the saved (or system) theme before first paint to prevent a flash --}}
    <script>
        (function () {
            var theme;
            try {
                theme = localStorage.getItem('testops-theme');
            } catch (error) {}

            if (!theme && window.matchMedia) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            theme = theme === 'dark' ? 'dark' : 'light';

            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    {{-- Keyboard accessibility: skip past the navigation chrome --}}
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to main content
    </a>

    {{-- Top navigation bar --}}
    <x-navbar />

    {{-- Mobile sidebar backdrop (drawer only, never shown on desktop) --}}
    <div id="sidebar-backdrop" aria-hidden="true" class="fixed inset-0 z-20 hidden bg-slate-950/50 lg:hidden"></div>

    {{-- Sidebar + main content --}}
    <div class="flex min-h-[calc(100dvh-4rem)]">
        <x-sidebar />

        <main id="main-content" class="min-w-0 flex-1">
            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Mobile sidebar drawer behaviour — vanilla JS, no framework required --}}
    <script>
        (function () {
            var sidebar = document.getElementById('app-sidebar');
            var backdrop = document.getElementById('sidebar-backdrop');
            var toggle = document.getElementById('sidebar-toggle');

            if (!sidebar || !backdrop || !toggle) {
                return;
            }

            var desktopQuery = window.matchMedia('(min-width: 1024px)');

            function isOpen() {
                return !sidebar.classList.contains('-translate-x-full');
            }

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full', 'invisible');
                backdrop.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Close navigation menu');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full', 'invisible');
                backdrop.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open navigation menu');
                document.body.style.overflow = '';
            }

            toggle.addEventListener('click', function () {
                if (isOpen()) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            backdrop.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isOpen() && !desktopQuery.matches) {
                    closeSidebar();
                    toggle.focus();
                }
            });

            // If the viewport grows to desktop while the drawer is open,
            // reset the mobile-only state (scroll lock, backdrop, aria).
            desktopQuery.addEventListener('change', function (event) {
                if (event.matches) {
                    closeSidebar();
                }
            });
        })();
    </script>
    {{-- Floating theme card (global component) --}}
    <x-theme-toggle />
</body>
</html>
