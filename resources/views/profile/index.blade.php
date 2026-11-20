@extends('layouts.app')

@section('title', 'Profile | TestOps')

@section('content')
    <section aria-labelledby="profile-heading" class="mx-auto max-w-3xl space-y-6">
        <header>
            <h1 id="profile-heading" class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                Profile
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if ($user->name)
                    Signed in as <span class="font-medium text-slate-700 dark:text-slate-300">{{ $user->name }}</span>.
                @else
                    You are signed in.
                @endif
                You can update your own details and email address here.
            </p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Personal details</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                These details identify you across the application. Your email address, password, and system
                role are managed separately.
            </p>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label for="profile-name" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Name
                    </label>
                    <input
                        id="profile-name"
                        type="text"
                        name="name"
                        maxlength="255"
                        autocomplete="name"
                        value="{{ old('name', $user->name) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="profile-contact-number" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Contact number
                    </label>
                    <input
                        id="profile-contact-number"
                        type="text"
                        name="contact_number"
                        maxlength="30"
                        autocomplete="tel"
                        value="{{ old('contact_number', $user->contact_number) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    @error('contact_number')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="profile-job-title" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Job title
                    </label>
                    <input
                        id="profile-job-title"
                        type="text"
                        name="job_title"
                        maxlength="100"
                        value="{{ old('job_title', $user->job_title) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    @error('job_title')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="profile-department" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Department
                    </label>
                    <input
                        id="profile-department"
                        type="text"
                        name="department"
                        maxlength="100"
                        value="{{ old('department', $user->department) }}"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    @error('department')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    >
                        Save details
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Email address</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Current verified email:
                <span class="font-medium text-slate-700 dark:text-slate-300">{{ $user->email }}</span>
            </p>

            @if ($pendingEmailChange !== null)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/70 dark:bg-amber-500/10">
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        A change to <span class="font-medium">{{ $pendingEmailChange->email }}</span> is awaiting
                        verification. Your current address stays active until you confirm the new one.
                    </p>

                    <form method="POST" action="{{ route('profile.email.cancel') }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label for="cancel-current-password" class="block text-xs font-medium text-amber-800 dark:text-amber-300">
                                Your current password <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="cancel-current-password"
                                type="password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                                class="mt-1 block w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-amber-900/70 dark:bg-slate-950 dark:text-slate-100"
                            >
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-amber-900/70 dark:bg-slate-900 dark:text-amber-300 dark:hover:bg-amber-950/40"
                            >
                                Cancel pending change
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('profile.email.update') }}" class="mt-5 space-y-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                @csrf
                <div>
                    <label for="new-email" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        New email address <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="new-email"
                        type="email"
                        name="email"
                        required
                        maxlength="255"
                        autocomplete="email"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                        We email a single-use verification link to this address. Your current email stays active
                        until you confirm the new one.
                    </p>
                </div>
                <div>
                    <label for="change-current-password" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Your current password <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="change-current-password"
                        type="password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    >
                        Send verification link
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Password</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Choose a new password. Your other signed-in sessions will be ended; this session stays signed in.
            </p>

            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label for="password-current-password" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Current password <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="password-current-password"
                        type="password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    @error('current_password')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password-new-password" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        New password <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="password-new-password"
                        type="password"
                        name="password"
                        required
                        minlength="12"
                        maxlength="64"
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                        At least 12 characters, including an uppercase letter, a lowercase letter, a number, and a symbol.
                    </p>
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password-confirmation" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Confirm new password <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="password-confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        minlength="12"
                        maxlength="64"
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                    >
                        Change password
                    </button>
                </div>
            </form>
        </div>

        {{-- §15.6: the account holder's own notification status. Only the
             user-safe fields are shown — never internal transport errors and
             no retry controls. --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Notification activity</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Recent emails sent to your account. Verification and password-reset emails can be requested
                    again through their normal workflows.
                </p>
            </div>

            @if ($notificationDeliveries->isNotEmpty())
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($notificationDeliveries as $delivery)
                        @php
                            $status = $delivery->retries->last()?->status ?? $delivery->status;
                        @endphp
                        <li class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ str_replace('_', ' ', ucfirst($delivery->notification_type)) }}
                                </p>
                                @if ($status === \App\Models\NotificationDelivery::STATUS_FAILED)
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $delivery->user_message ?: 'Your email could not be delivered. Please try again later.' }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($status === \App\Models\NotificationDelivery::STATUS_DELIVERED)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">Delivered</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400">Failed</span>
                                @endif
                                <time class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400" datetime="{{ $delivery->created_at?->toIso8601String() }}">
                                    {{ $delivery->created_at?->format('M d, Y H:i') ?? '—' }}
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    No notifications sent to your account yet.
                </p>
            @endif
        </div>
    </section>
@endsection
