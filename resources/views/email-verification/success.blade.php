<x-layout-guest title="Email verified">
    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">Email verified</h1>

    <div role="status" class="mt-6 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm leading-relaxed text-emerald-700 dark:text-emerald-300">
        Your account is active. You can now sign in with your email address and password.
    </div>

    <p class="mt-6">
        <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
            Sign in
        </a>
    </p>
</x-layout-guest>
