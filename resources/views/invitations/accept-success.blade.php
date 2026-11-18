<x-layout-guest title="Invitation accepted">
    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">Password set</h1>
    <p class="mt-2.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">
        Your account password is set for {{ $email }}. We sent a verification link to the
        same address; open it to finish activating your account.
    </p>

    <div role="status" class="mt-6 rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm leading-relaxed text-emerald-700 dark:text-emerald-300">
        Check your email inbox for the verification link. The link expires within a few minutes.
    </div>

    <p class="mt-6">
        <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
            Go to sign in
        </a>
    </p>
</x-layout-guest>
