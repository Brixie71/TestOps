<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to TestOps — the software testing management platform for QA teams and developers.">
    <title>Sign In | TestOps</title>

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

    {!! Vite::fonts(null) !!}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream-50 font-sans text-stone-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-14 sm:px-6">

        {{-- Warm cream backdrop with a faint blueprint grid — indigo accents in dark mode --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid dark:opacity-60"></div>
        <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-[-8rem] h-96 w-[42rem] max-w-full -translate-x-1/2 rounded-full bg-amber-200/50 blur-3xl dark:bg-indigo-600/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-cream-300/60 blur-3xl dark:bg-violet-600/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-20 h-72 w-72 rounded-full bg-amber-100/70 blur-3xl dark:bg-emerald-600/10"></div>

        <div class="relative w-full max-w-[26.5rem]">

            {{-- ========================================================
                 Login panel — centered, formal cream white surface
            ========================================================= --}}
            <section class="overflow-hidden rounded-2xl border border-stone-300/70 bg-white shadow-[0_24px_64px_-16px_rgb(120_90_50/0.22)] dark:border-slate-700/70 dark:bg-slate-900 dark:shadow-[0_24px_64px_-16px_rgb(0_0_0/0.55)]">
                <header class="flex flex-col items-center border-b border-stone-200/80 px-8 pb-7 pt-9 text-center dark:border-slate-800/80 dark:pb-6">
                    <svg class="h-12 w-12 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="testops-mark-login" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#6366F1" />
                                <stop offset="1" stop-color="#4F46E5" />
                            </linearGradient>
                        </defs>
                        <rect width="40" height="40" rx="10" fill="url(#testops-mark-login)" />
                        <rect x="0.5" y="0.5" width="39" height="39" rx="9.5" stroke="#FFFFFF" stroke-opacity="0.18" />
                        <path d="M12 20.75L17.25 26L28 14.75" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="mt-3.5 text-lg font-bold leading-none tracking-tight text-stone-900 dark:text-slate-100">Test<span class="text-indigo-600 dark:text-indigo-400">Ops</span></p>
                    <p class="mt-2 text-[10px] font-semibold uppercase tracking-[0.24em] text-stone-500 dark:text-slate-500">Quality Assurance Platform</p>
                </header>

                <div class="px-8 py-8 sm:px-10 sm:py-9">
                    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">Welcome back</h1>
                    <p class="mt-2.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">
                        Sign in to your TestOps workspace to manage test plans, runs, and releases.
                    </p>

                    @if (session('status'))
                        <div role="status" class="mt-6 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm leading-relaxed text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div id="auth-errors" role="alert" class="mt-6 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3.5 dark:border-red-500/25 dark:bg-red-500/10">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <div class="min-w-0 text-sm leading-relaxed text-red-700 dark:text-red-300">
                                @if ($errors->count() > 1)
                                    <ul class="list-inside list-disc space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>{{ $errors->first() }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @php
                        $emailHasError = $errors->has('email');
                        $passwordHasError = $errors->has('password');
                    @endphp

                    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-stone-700 dark:text-slate-300">
                                Email address
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                autocomplete="username"
                                required
                                autofocus
                                placeholder="you@company.com"
                                aria-invalid="{{ $emailHasError ? 'true' : 'false' }}"
                                @if ($emailHasError) aria-describedby="auth-errors" @endif
                                class="block w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/10 {{ $emailHasError ? '!border-red-500 focus:!border-red-500 focus:!ring-red-500/10' : '' }}"
                            >
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="password" class="block text-sm font-medium text-stone-700 dark:text-slate-300">Password</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-indigo-600 transition hover:text-indigo-700 focus:outline-none focus-visible:underline dark:text-indigo-400 dark:hover:text-indigo-300">
                                        Forgot password?
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                    aria-invalid="{{ $passwordHasError ? 'true' : 'false' }}"
                                    @if ($passwordHasError) aria-describedby="auth-errors" @endif
                                    class="block w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 pr-11 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/10 {{ $passwordHasError ? '!border-red-500 focus:!border-red-500 focus:!ring-red-500/10' : '' }}"
                                >
                                <button
                                    type="button"
                                    id="toggle-password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    aria-label="Show password"
                                    class="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-stone-500 transition hover:bg-stone-100 hover:text-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-slate-300"
                                >
                                    <svg data-password-icon="show" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg data-password-icon="hide" class="hidden h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="remember" class="flex w-fit cursor-pointer select-none items-center gap-2.5 text-sm text-stone-600 transition hover:text-stone-900 dark:text-slate-400 dark:hover:text-slate-300">
                                <input id="remember" name="remember" type="checkbox" value="1" class="peer sr-only">
                                <span aria-hidden="true" class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-[5px] border border-stone-400 bg-white text-white transition peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:[&>svg]:scale-100 peer-checked:[&>svg]:opacity-100 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/40 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-white dark:border-slate-600 dark:bg-slate-950 dark:peer-checked:border-indigo-500 dark:peer-checked:bg-indigo-500 dark:peer-focus-visible:ring-offset-slate-950">
                                    <svg class="h-3 w-3 scale-75 opacity-0 transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                                Remember me
                            </label>
                        </div>

                        <button
                            type="submit"
                            class="group flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 outline-none transition hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gradient-to-b dark:from-indigo-500 dark:to-indigo-600 dark:shadow-indigo-950/40 dark:hover:from-indigo-400 dark:hover:to-indigo-500 dark:active:from-indigo-600 dark:active:to-indigo-700 dark:focus-visible:ring-offset-slate-950"
                        >
                            Sign in
                            <svg class="h-4 w-4 transition-transform duration-150 group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </form>
                </div>
            </section>

            <p class="mt-8 text-center text-xs text-stone-500 dark:text-slate-500">
                TestOps &copy; {{ date('Y') }} &middot; All Rights Reserved
            </p>
            <p class="mt-2 text-center text-sm font-medium text-stone-600 dark:text-slate-400">
                Powered by
                <a href="https://laravel.com" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 font-semibold text-[#FF2D20] transition-colors hover:text-[#FF2D20]/80 focus:outline-none focus-visible:underline">
                    Laravel
                    <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M23.642 5.43a.364.364 0 01.014.1v5.149c0 .135-.073.26-.189.326l-4.323 2.49v4.934a.378.378 0 01-.188.326L9.93 23.949a.316.316 0 01-.066.027c-.008.002-.016.008-.024.01a.348.348 0 01-.192 0c-.011-.002-.02-.008-.03-.012-.02-.008-.042-.014-.062-.025L.533 18.755a.376.376 0 01-.189-.326V2.974c0-.033.005-.066.014-.098.003-.012.01-.02.014-.032a.369.369 0 01.023-.058c.004-.013.015-.022.023-.033l.033-.045c.012-.01.025-.018.037-.027.014-.012.027-.024.041-.034H.53L5.043.05a.375.375 0 01.375 0L9.93 2.647h.002c.015.01.027.021.04.033l.038.027c.013.014.02.03.033.045.008.011.02.021.025.033.01.02.017.038.024.058.003.011.01.021.013.032.01.031.014.064.014.098v9.652l3.76-2.164V5.527c0-.033.004-.066.013-.098.003-.01.01-.02.013-.032a.487.487 0 01.024-.059c.007-.012.018-.02.025-.033.012-.015.021-.03.033-.043.012-.012.025-.02.037-.028.014-.01.026-.023.041-.032h.001l4.513-2.598a.375.375 0 01.375 0l4.513 2.598c.016.01.027.021.042.031.012.01.025.018.036.028.013.014.022.03.034.044.008.012.019.021.024.033.011.02.018.04.024.06.006.01.012.021.015.032zm-.74 5.032V6.179l-1.578.908-2.182 1.256v4.283zm-4.51 7.75v-4.287l-2.147 1.225-6.126 3.498v4.325zM1.093 3.624v14.588l8.273 4.761v-4.325l-4.322-2.445-.002-.003H5.04c-.014-.01-.025-.021-.04-.031-.011-.01-.024-.018-.035-.027l-.001-.002c-.013-.012-.021-.025-.031-.04-.01-.011-.021-.022-.028-.036h-.002c-.008-.014-.013-.031-.02-.047-.006-.016-.014-.027-.018-.043a.49.49 0 01-.008-.057c-.002-.014-.006-.027-.006-.041V5.789l-2.18-1.257zM5.23.81L1.47 2.974l3.76 2.164 3.758-2.164zm1.956 13.505l2.182-1.256V3.624l-1.58.91-2.182 1.255v9.435zm11.581-10.95l-3.76 2.163 3.76 2.163 3.759-2.164zm-.376 4.978L16.21 7.087 14.63 6.18v4.283l2.182 1.256 1.58.908zm-8.65 9.654l5.514-3.148 2.756-1.572-3.757-2.163-4.323 2.489-3.941 2.27z" />
                    </svg>
                </a>
            </p>
        </div>
    </main>

    {{-- Floating theme card (global component) --}}
    <x-theme-toggle />

    {{-- Password visibility toggle — plain JavaScript, no framework required --}}
    <script>
        (function () {
            var toggle = document.getElementById('toggle-password');
            var password = document.getElementById('password');

            if (!toggle || !password) {
                return;
            }

            var showIcon = toggle.querySelector('[data-password-icon="show"]');
            var hideIcon = toggle.querySelector('[data-password-icon="hide"]');

            toggle.addEventListener('click', function () {
                var isHidden = password.getAttribute('type') === 'password';

                password.setAttribute('type', isHidden ? 'text' : 'password');
                toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

                showIcon.classList.toggle('hidden', isHidden);
                hideIcon.classList.toggle('hidden', !isHidden);

                password.focus({ preventScroll: true });
            });
        })();
    </script>
</body>
</html>
