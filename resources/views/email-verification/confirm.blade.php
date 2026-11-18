<x-layout-guest title="Confirm email verification">
    <h1 class="font-serif text-[26px] font-semibold leading-tight tracking-tight text-stone-900 dark:text-slate-50 sm:text-[28px]">Verify your email</h1>
    <p class="mt-2.5 text-sm leading-relaxed text-stone-500 dark:text-slate-400">
        Click the button below to confirm this email address and activate your account.
    </p>

    <form method="POST" action="{{ route('email-verification.store') }}" class="mt-8">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <button
            type="submit"
            class="flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 outline-none transition hover:bg-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:bg-indigo-700 dark:bg-gradient-to-b dark:from-indigo-500 dark:to-indigo-600 dark:focus-visible:ring-offset-slate-950"
        >
            Verify email
        </button>
    </form>
</x-layout-guest>
