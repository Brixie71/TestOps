<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to TestOps — the software testing management platform for QA teams and developers.">
    <title>Sign In | TestOps</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
    <main class="flex min-h-screen flex-col lg:grid lg:grid-cols-2">

        {{-- ========================================================
             Brand panel — full experience on desktop, hidden on
             mobile (mobile gets the simplified brand bar below).
        ========================================================= --}}
        <aside class="relative hidden overflow-hidden border-r border-slate-800/70 bg-slate-900 lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
            {{-- Decorative surface layers --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -left-28 -top-28 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-20 h-80 w-80 rounded-full bg-violet-600/10 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-slate-900 to-transparent"></div>

            {{-- Logo lockup --}}
            <div class="relative flex items-center gap-3.5">
                <svg class="h-11 w-11 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="testops-mark-desktop" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#6366F1" />
                            <stop offset="1" stop-color="#4F46E5" />
                        </linearGradient>
                    </defs>
                    <rect width="40" height="40" rx="10" fill="url(#testops-mark-desktop)" />
                    <rect x="0.5" y="0.5" width="39" height="39" rx="9.5" stroke="#FFFFFF" stroke-opacity="0.18" />
                    <path d="M12 20.75L17.25 26L28 14.75" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div>
                    <p class="text-lg font-bold leading-none tracking-tight text-white">Test<span class="text-indigo-400">Ops</span></p>
                    <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">QA Platform</p>
                </div>
            </div>

            {{-- Value proposition + product visual --}}
            <div class="relative flex-1 py-14">
                <div class="max-w-lg">
                    <p class="text-3xl font-bold leading-tight tracking-tight text-white xl:text-4xl">
                        Quality Assurance.
                        <span class="block bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-transparent">Simplified.</span>
                    </p>
                    <p class="mt-5 max-w-md text-[15px] leading-relaxed text-slate-400">
                        TestOps is the testing management platform where QA analysts, developers, and
                        stakeholders plan test cycles, monitor execution, and ship every release with confidence.
                    </p>

                    <ul class="mt-8 space-y-3.5">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-indigo-400/20 bg-indigo-500/10 text-indigo-300">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            <span class="text-sm leading-6 text-slate-300">Plan test cases, suites, and release cycles in one shared workspace.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-indigo-400/20 bg-indigo-500/10 text-indigo-300">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            <span class="text-sm leading-6 text-slate-300">Track execution, defects, and coverage with real-time dashboards.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-indigo-400/20 bg-indigo-500/10 text-indigo-300">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            <span class="text-sm leading-6 text-slate-300">Integrate CI/CD pipelines and automation frameworks out of the box.</span>
                        </li>
                    </ul>

                    {{-- Product visual: live test run snapshot (decorative) --}}
                    <div class="relative mt-10 max-w-md" aria-hidden="true">
                        <div class="rounded-2xl border border-white/10 bg-slate-950/60 shadow-2xl shadow-black/40 backdrop-blur-sm">
                            <div class="flex items-center justify-between border-b border-white/5 px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="relative flex h-2 w-2">
                                        <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60 motion-safe:animate-ping"></span>
                                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                                    </span>
                                    <p class="text-sm font-semibold text-slate-200">Regression Suite</p>
                                </div>
                                <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-medium text-slate-400">Run #4821</span>
                            </div>

                            <div class="space-y-0.5 px-2.5 py-3">
                                <div class="flex items-center gap-3 rounded-lg px-2.5 py-2">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    </span>
                                    <p class="flex-1 truncate text-[13px] font-medium text-slate-300">Authentication flow</p>
                                    <span class="w-10 shrink-0 text-right text-[11px] font-medium tabular-nums text-slate-500">1.24s</span>
                                    <span class="w-16 shrink-0 rounded-full bg-emerald-400/10 px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wider text-emerald-300">Passed</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg px-2.5 py-2">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    </span>
                                    <p class="flex-1 truncate text-[13px] font-medium text-slate-300">Checkout API</p>
                                    <span class="w-10 shrink-0 text-right text-[11px] font-medium tabular-nums text-slate-500">0.86s</span>
                                    <span class="w-16 shrink-0 rounded-full bg-emerald-400/10 px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wider text-emerald-300">Passed</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg px-2.5 py-2">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    </span>
                                    <p class="flex-1 truncate text-[13px] font-medium text-slate-300">Payment gateway</p>
                                    <span class="w-10 shrink-0 text-right text-[11px] font-medium tabular-nums text-slate-500">2.10s</span>
                                    <span class="w-16 shrink-0 rounded-full bg-emerald-400/10 px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wider text-emerald-300">Passed</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg px-2.5 py-2">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-400/10 text-indigo-400">
                                        <svg class="h-3.5 w-3.5 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                    </span>
                                    <p class="flex-1 truncate text-[13px] font-medium text-slate-300">Email notifications</p>
                                    <span class="w-10 shrink-0 text-right text-[11px] font-medium tabular-nums text-slate-600">&mdash;</span>
                                    <span class="w-16 shrink-0 rounded-full bg-indigo-400/10 px-2 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wider text-indigo-300">Running</span>
                                </div>
                            </div>

                            <div class="border-t border-white/5 px-5 py-4">
                                <div class="flex items-center justify-between text-[11px] font-medium">
                                    <span class="text-slate-400"><span class="text-slate-200">247</span> of 262 tests passed</span>
                                    <span class="text-emerald-400">94.3%</span>
                                </div>
                                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-white/5">
                                    <div class="h-full w-[94%] rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400"></div>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -bottom-7 right-4 hidden items-center gap-3 rounded-xl border border-white/10 bg-slate-900 px-4 py-3 shadow-xl shadow-black/40 xl:flex">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-400/10 text-emerald-400">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                            </span>
                            <div>
                                <p class="text-xs font-semibold text-slate-200">Release 4.2 approved</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">All quality gates passed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel footer --}}
            <div class="relative flex items-center justify-between text-xs text-slate-500">
                <p>&copy; {{ date('Y') }} TestOps. All rights reserved.</p>
                <p class="hidden xl:block">Built for QA &amp; engineering teams</p>
            </div>
        </aside>

        {{-- Simplified brand bar — mobile / tablet --}}
        <header class="flex items-center justify-center gap-2.5 border-b border-slate-800/70 bg-slate-950 px-6 py-5 lg:hidden">
            <svg class="h-8 w-8 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <defs>
                    <linearGradient id="testops-mark-mobile" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#6366F1" />
                        <stop offset="1" stop-color="#4F46E5" />
                    </linearGradient>
                </defs>
                <rect width="40" height="40" rx="10" fill="url(#testops-mark-mobile)" />
                <path d="M12 20.75L17.25 26L28 14.75" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <p class="text-lg font-bold tracking-tight text-white">Test<span class="text-indigo-400">Ops</span></p>
        </header>

        {{-- ========================================================
             Auth panel
        ========================================================= --}}
        <section class="relative flex flex-1 items-center justify-center bg-slate-950 px-6 py-12 sm:px-10">
            <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-0 h-72 w-[42rem] max-w-full -translate-x-1/2 rounded-full bg-indigo-600/10 blur-3xl"></div>

            <div class="relative w-full max-w-[26rem]">
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-[28px]">Welcome back</h1>
                <p class="mt-2.5 text-sm leading-relaxed text-slate-400">
                    Sign in to your TestOps workspace to manage test plans, runs, and releases.
                </p>

                @if (session('status'))
                    <div role="status" class="mt-6 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm leading-relaxed text-emerald-300">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div id="auth-errors" role="alert" class="mt-6 flex items-start gap-3 rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3.5">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <div class="min-w-0 text-sm leading-relaxed text-red-300">
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
                        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">
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
                            class="block w-full rounded-lg border bg-slate-900/70 px-4 py-2.5 text-sm text-slate-100 shadow-sm shadow-black/20 outline-none transition placeholder:text-slate-500 focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50 {{ $emailHasError ? 'border-red-500/60 hover:border-red-500/60 focus:border-red-500 focus:ring-red-500/10' : 'border-slate-700/80 hover:border-slate-600 focus:border-indigo-500 focus:ring-indigo-500/10' }}"
                        >
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-xs font-medium text-indigo-400 transition hover:text-indigo-300 focus:outline-none focus-visible:underline">
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
                                class="block w-full rounded-lg border bg-slate-900/70 px-4 py-2.5 pr-11 text-sm text-slate-100 shadow-sm shadow-black/20 outline-none transition placeholder:text-slate-500 focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50 {{ $passwordHasError ? 'border-red-500/60 hover:border-red-500/60 focus:border-red-500 focus:ring-red-500/10' : 'border-slate-700/80 hover:border-slate-600 focus:border-indigo-500 focus:ring-indigo-500/10' }}"
                            >
                            <button
                                type="button"
                                id="toggle-password"
                                aria-controls="password"
                                aria-pressed="false"
                                aria-label="Show password"
                                class="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 transition hover:bg-white/5 hover:text-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40"
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
                        <label for="remember" class="flex w-fit cursor-pointer select-none items-center gap-2.5 text-sm text-slate-400 transition hover:text-slate-300">
                            <input id="remember" name="remember" type="checkbox" value="1" class="peer sr-only">
                            <span aria-hidden="true" class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-[5px] border border-slate-600 bg-slate-900 text-white transition peer-checked:border-indigo-500 peer-checked:bg-indigo-500 peer-checked:[&>svg]:scale-100 peer-checked:[&>svg]:opacity-100 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/40 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-slate-950">
                                <svg class="h-3 w-3 scale-75 opacity-0 transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            Remember me
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-b from-indigo-500 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-950/40 outline-none transition hover:from-indigo-400 hover:to-indigo-500 hover:shadow-indigo-600/25 focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 active:from-indigo-600 active:to-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Sign in
                        <svg class="h-4 w-4 transition-transform duration-150 group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </form>

                <footer class="mt-10 border-t border-slate-800/70 pt-6">
                    <p class="text-center text-xs text-slate-500">
                        TestOps &copy; {{ date('Y') }} &middot; Quality Assurance. Simplified.
                    </p>
                </footer>
            </div>
        </section>
    </main>

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
