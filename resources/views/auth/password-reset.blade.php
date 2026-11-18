<x-layout-guest title="Reset password">
    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">Choose a new password</h1>
    <p class="mt-2.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">
        An administrator has required a password reset for this account. Set a new
        password to restore access. The link works once.
    </p>

    @if ($errors->any())
        <div role="alert" class="mt-6 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3.5 dark:border-red-500/25 dark:bg-red-500/10">
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

    <form method="POST" action="{{ route('password-reset.store', ['token' => $token]) }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-stone-700 dark:text-slate-300">New password</label>
            <p class="text-xs text-stone-500 dark:text-slate-400">At least 12 characters with uppercase, lowercase, number, and symbol.</p>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
                minlength="12"
                maxlength="64"
                aria-describedby="password-confirmation"
                class="mt-1.5 block w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/10"
            >
        </div>

        <div>
            <label for="password-confirmation" class="mb-1.5 block text-sm font-medium text-stone-700 dark:text-slate-300">Confirm new password</label>
            <input
                id="password-confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                minlength="12"
                maxlength="64"
                class="block w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/10"
            >
        </div>

        <button
            type="submit"
            class="flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 outline-none transition hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:bg-indigo-700 dark:bg-gradient-to-b dark:from-indigo-500 dark:to-indigo-600 dark:focus-visible:ring-offset-slate-950"
        >
            Reset password
        </button>
    </form>
</x-layout-guest>
