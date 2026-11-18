<x-layout-guest title="Invitation link unavailable">
    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">{{ $title }}</h1>

    <div role="alert" class="mt-6 rounded-lg border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm leading-relaxed text-amber-700 dark:text-amber-300">
        {{ $message }}
    </div>

    <p class="mt-6 text-sm text-stone-500 dark:text-slate-400">
        If you still have your recovery code, you can request a fresh link below.
    </p>

    <p class="mt-3">
        <a href="{{ route('invitations.recover.show') }}" class="text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
            Recover the invitation
        </a>
    </p>
</x-layout-guest>
