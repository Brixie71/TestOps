<x-layout-guest title="Recover invitation">
    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">Recover the invitation</h1>
    <p class="mt-2.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">
        Enter the email address plus the recovery code from your invitation email to receive
        a fresh invitation link.
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

    <form method="POST" action="{{ route('invitations.recover.store') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-stone-700 dark:text-slate-300">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                class="block w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/10"
            >
        </div>

        <div>
            <label for="recovery-code" class="mb-1.5 block text-sm font-medium text-stone-700 dark:text-slate-300">Recovery code</label>
            <input
                id="recovery-code"
                name="recovery_code"
                type="text"
                value="{{ old('recovery_code') }}"
                required
                class="block w-full rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm tracking-wide text-stone-900 shadow-sm outline-none transition placeholder:text-stone-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:focus:border-indigo-500 dark:focus:ring-indigo-500/10"
            >
        </div>

        <button
            type="submit"
            class="flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 outline-none transition hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:bg-indigo-700 dark:bg-gradient-to-b dark:from-indigo-500 dark:to-indigo-600 dark:focus-visible:ring-offset-slate-950"
        >
            Send fresh link
        </button>
    </form>
</x-layout-guest>
