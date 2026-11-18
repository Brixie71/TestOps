{{--
    Minimal guest layout for account creation and verification pages.
    Guests have no app shell; the layout mirrors the login page surface.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'TestOps' }}</title>

    {!! Vite::fonts(null) !!}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream-50 font-sans text-stone-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-14 sm:px-6">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid dark:opacity-60"></div>

        <div class="relative w-full max-w-[26.5rem]">
            <section class="overflow-hidden rounded-2xl border border-stone-300/70 bg-white shadow-[0_24px_64px_-16px_rgb(120_90_50/0.22)] dark:border-slate-700/70 dark:bg-slate-900 dark:shadow-[0_24px_64px_-16px_rgb(0_0_0/0.55)]">
                <header class="flex flex-col items-center border-b border-stone-200/80 px-8 pb-7 pt-9 text-center dark:border-slate-800/80 dark:pb-6">
                    <svg class="h-12 w-12 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="testops-mark-guest" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#6366F1" />
                                <stop offset="1" stop-color="#4F46E5" />
                            </linearGradient>
                        </defs>
                        <rect width="40" height="40" rx="10" fill="url(#testops-mark-guest)" />
                        <rect x="0.5" y="0.5" width="39" height="39" rx="9.5" stroke="#FFFFFF" stroke-opacity="0.18" />
                        <path d="M12 20.75L17.25 26L28 14.75" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="mt-3.5 text-lg font-bold leading-none tracking-tight text-stone-900 dark:text-slate-100">Test<span class="text-indigo-600 dark:text-indigo-400">Ops</span></p>
                </header>

                <div class="px-8 py-8 sm:px-10 sm:py-9">
                    {{ $slot }}
                </div>
            </section>

            <p class="mt-8 text-center text-xs text-stone-500 dark:text-slate-500">
                TestOps &copy; {{ date('Y') }} &middot; All Rights Reserved
            </p>
        </div>
    </main>

    <x-theme-toggle />
</body>
</html>
