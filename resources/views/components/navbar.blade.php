{{-- Global authenticated top navigation bar.
     Intentionally minimal: application branding (left) and Logout (right). --}}

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
    <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-2">
            {{-- Mobile sidebar toggle --}}
            <button
                type="button"
                id="sidebar-toggle"
                aria-controls="app-sidebar"
                aria-expanded="false"
                aria-label="Open navigation menu"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 lg:hidden"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            {{-- Application branding --}}
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
                <p class="text-base font-bold tracking-tight text-slate-900">
                    Test<span class="text-indigo-600">Ops</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            {{-- Reserved for future global navbar items --}}

            {{-- Logout (POST + CSRF via the existing auth backend) --}}
            <form method="POST" action="{{ route('logout') }}" class="flex items-center">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 active:bg-slate-100"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 8.25V6A2.25 2.25 0 0013.5 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25h7.5a2.25 2.25 0 002.25-2.25v-2.25" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12h9m0 0l-3-3m3 3l-3 3" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </div>
</header>
