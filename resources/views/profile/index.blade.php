@extends('layouts.app')

@section('title', 'Profile | TestOps')

@php
    $cardClass = 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900';
    $cardHeaderClass = 'flex items-start gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6';
    $cardBodyClass = 'px-5 py-5 sm:px-6';
    $cardFooterClass = 'flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-slate-50/60 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/40 sm:px-6';
    $iconTileClass = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400';
    $labelClass = 'block text-xs font-medium text-slate-500 dark:text-slate-400';
    $inputClass = 'mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500';
    $primaryButtonClass = 'inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900';
    $chipClass = 'inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300';
    $initials = \Illuminate\Support\Str::of($user->name ?: $user->email)
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn ($part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))
        ->implode('') ?: '?';
@endphp

@section('content')
    <section aria-labelledby="profile-heading" class="mx-auto max-w-5xl space-y-6">
        <header>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600 dark:text-indigo-400">
                Account settings
            </p>
            <h1 id="profile-heading" class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                Profile
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                @if ($user->name)
                    Signed in as <span class="font-medium text-slate-700 dark:text-slate-300">{{ $user->name }}</span>.
                @else
                    You are signed in.
                @endif
                Manage your personal details, sign-in credentials, and notification preferences here.
            </p>
        </header>

        {{-- Identity summary: read-only snapshot of the account this page belongs to. --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-500/10 via-transparent to-transparent dark:from-indigo-500/15" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-lg font-semibold tracking-wide text-white shadow-sm">
                        {{ $initials }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-slate-900 dark:text-slate-100">
                            {{ $user->name ?: 'Unnamed account' }}
                        </p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                        @if ($user->job_title || $user->department)
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                @if ($user->job_title)
                                    <span class="{{ $chipClass }}">{{ $user->job_title }}</span>
                                @endif
                                @if ($user->department)
                                    <span class="{{ $chipClass }}">{{ $user->department }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    @if ($user->contact_number)
                        <span class="{{ $chipClass }}">{{ $user->contact_number }}</span>
                    @endif
                    @if ($user->email_verified_at)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            Verified
                        </span>
                    @endif
                    @if ($user->created_at)
                        <span class="{{ $chipClass }}">
                            Member since <x-local-time :at="$user->created_at" format="M Y" />
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if (session('status'))
            <div role="status" class="flex items-start gap-3 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-2">
            {{-- Personal details (§3.1, §6.5): name, contact number, job title, department. --}}
            <div class="{{ $cardClass }} lg:col-span-2">
                <div class="{{ $cardHeaderClass }}">
                    <span class="{{ $iconTileClass }}">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Personal details</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            These details identify you across the application. Your email address, password, and
                            system role are managed separately.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="{{ $cardBodyClass }} grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="profile-name" class="{{ $labelClass }}">Name</label>
                            <input
                                id="profile-name"
                                type="text"
                                name="name"
                                maxlength="255"
                                autocomplete="name"
                                value="{{ old('name', $user->name) }}"
                                class="{{ $inputClass }}"
                            >
                            @error('name')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="profile-contact-number" class="{{ $labelClass }}">Contact number</label>
                            <input
                                id="profile-contact-number"
                                type="text"
                                name="contact_number"
                                maxlength="30"
                                autocomplete="tel"
                                value="{{ old('contact_number', $user->contact_number) }}"
                                class="{{ $inputClass }}"
                            >
                            @error('contact_number')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="profile-job-title" class="{{ $labelClass }}">Job title</label>
                            <input
                                id="profile-job-title"
                                type="text"
                                name="job_title"
                                maxlength="100"
                                value="{{ old('job_title', $user->job_title) }}"
                                class="{{ $inputClass }}"
                            >
                            @error('job_title')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="profile-department" class="{{ $labelClass }}">Department</label>
                            <input
                                id="profile-department"
                                type="text"
                                name="department"
                                maxlength="100"
                                value="{{ old('department', $user->department) }}"
                                class="{{ $inputClass }}"
                            >
                            @error('department')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="{{ $cardFooterClass }}">
                        <p class="mr-auto hidden text-xs text-slate-400 dark:text-slate-500 sm:block">
                            Changes are recorded in the audit log.
                        </p>
                        <button type="submit" class="{{ $primaryButtonClass }}">
                            Save details
                        </button>
                    </div>
                </form>
            </div>

            {{-- Email address (§9.6): current address stays active until the new one is verified. --}}
            <div class="{{ $cardClass }}">
                <div class="{{ $cardHeaderClass }}">
                    <span class="{{ $iconTileClass }}">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2.5" y="4.5" width="19" height="15" rx="2.5" />
                            <path d="m3 6 9 6.5L21 6" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Email address</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Changing it sends a single-use verification link. The current address stays active
                            until you confirm the new one.
                        </p>
                    </div>
                </div>

                <div class="{{ $cardBodyClass }} space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/40">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Current email
                            </p>
                            <p class="mt-0.5 truncate text-sm font-medium text-slate-700 dark:text-slate-300">{{ $user->email }}</p>
                        </div>
                        @if ($user->email_verified_at)
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                Verified
                            </span>
                        @else
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                Unverified
                            </span>
                        @endif
                    </div>

                    @if ($pendingEmailChange !== null)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/70 dark:bg-amber-500/10">
                            <p class="text-sm text-amber-800 dark:text-amber-300">
                                A change to <span class="font-semibold">{{ $pendingEmailChange->email }}</span> is awaiting
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
                                        class="mt-1.5 block w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-amber-900/70 dark:bg-slate-950 dark:text-slate-100"
                                    >
                                </div>
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 transition-colors hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-amber-900/70 dark:bg-slate-900 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                    >
                                        Cancel pending change
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('profile.email.update') }}">
                    @csrf

                    <div class="{{ $cardBodyClass }} space-y-4 border-t border-slate-200 dark:border-slate-800">
                        <div>
                            <label for="new-email" class="{{ $labelClass }}">
                                New email address <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="new-email"
                                type="email"
                                name="email"
                                required
                                maxlength="255"
                                autocomplete="email"
                                placeholder="you@example.com"
                                class="{{ $inputClass }}"
                            >
                            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                We email a single-use verification link to this address. Your current email stays
                                active until you confirm the new one.
                            </p>
                        </div>
                        <div>
                            <label for="change-current-password" class="{{ $labelClass }}">
                                Your current password <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="change-current-password"
                                type="password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                                class="{{ $inputClass }}"
                            >
                        </div>
                    </div>

                    <div class="{{ $cardFooterClass }}">
                        <button type="submit" class="{{ $primaryButtonClass }}">
                            Send verification link
                        </button>
                    </div>
                </form>
            </div>

            {{-- Password (§11.4): self-service change; other sessions are ended. --}}
            <div class="{{ $cardClass }}">
                <div class="{{ $cardHeaderClass }}">
                    <span class="{{ $iconTileClass }}">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10.5" width="16" height="10" rx="2" />
                            <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Password</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Choose a new password. Your other signed-in sessions will be ended; this session stays
                            signed in.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="{{ $cardBodyClass }} space-y-4">
                        <div>
                            <label for="password-current-password" class="{{ $labelClass }}">
                                Current password <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="password-current-password"
                                type="password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                                class="{{ $inputClass }}"
                            >
                            @error('current_password')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password-new-password" class="{{ $labelClass }}">
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
                                class="{{ $inputClass }}"
                            >
                            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                At least 12 characters, including an uppercase letter, a lowercase letter, a number,
                                and a symbol.
                            </p>
                            @error('password')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password-confirmation" class="{{ $labelClass }}">
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
                                class="{{ $inputClass }}"
                            >
                        </div>
                    </div>

                    <div class="{{ $cardFooterClass }}">
                        <button type="submit" class="{{ $primaryButtonClass }}">
                            Change password
                        </button>
                    </div>
                </form>
            </div>

            {{-- Notification preferences (§14.3): security mail is mandatory. --}}
            <div class="{{ $cardClass }} lg:col-span-2">
                <div class="{{ $cardHeaderClass }}">
                    <span class="{{ $iconTileClass }}">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.7 21a2 2 0 0 1-3.4 0" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Email notifications</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Security and account activity emails are always sent. Choose which optional categories
                            you want to receive.
                        </p>
                    </div>
                </div>

                <div class="{{ $cardBodyClass }} space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/40">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Security and account activity</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Password resets, email verification, and account changes.
                            </p>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Always on
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.notifications.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="{{ $cardBodyClass }} grid gap-3 pt-0 sm:grid-cols-2">
                        <label for="notification-project-activity" class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-800/40">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">Project activity</span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    Updates about projects you own or belong to.
                                </span>
                            </span>
                            <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center">
                                <input
                                    id="notification-project-activity"
                                    type="checkbox"
                                    name="project_activity_enabled"
                                    value="1"
                                    @checked((bool) old('project_activity_enabled', $notificationPreferences?->project_activity_enabled ?? true))
                                    class="peer sr-only"
                                >
                                <span class="absolute inset-0 rounded-full bg-slate-200 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 dark:bg-slate-700 dark:peer-focus-visible:ring-offset-slate-900" aria-hidden="true"></span>
                                <span class="pointer-events-none absolute left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5" aria-hidden="true"></span>
                            </span>
                        </label>

                        <label for="notification-system-announcements" class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-800/40">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">System announcements</span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    Maintenance notices and release announcements.
                                </span>
                            </span>
                            <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center">
                                <input
                                    id="notification-system-announcements"
                                    type="checkbox"
                                    name="system_announcements_enabled"
                                    value="1"
                                    @checked((bool) old('system_announcements_enabled', $notificationPreferences?->system_announcements_enabled ?? true))
                                    class="peer sr-only"
                                >
                                <span class="absolute inset-0 rounded-full bg-slate-200 transition-colors peer-checked:bg-indigo-600 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-2 dark:bg-slate-700 dark:peer-focus-visible:ring-offset-slate-900" aria-hidden="true"></span>
                                <span class="pointer-events-none absolute left-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5" aria-hidden="true"></span>
                            </span>
                        </label>
                    </div>

                    <div class="{{ $cardFooterClass }}">
                        <button type="submit" class="{{ $primaryButtonClass }}">
                            Save preferences
                        </button>
                    </div>
                </form>
            </div>

            {{-- §15.6: the account holder's own notification status. Only the
                 user-safe fields are shown — never internal transport errors and
                 no retry controls. --}}
            <div class="{{ $cardClass }} lg:col-span-2">
                <div class="{{ $cardHeaderClass }}">
                    <span class="{{ $iconTileClass }}">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 12h-4l-3 8-6-16-3 8H2" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Notification activity</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Recent emails sent to your account. Verification and password-reset emails can be
                            requested again through their normal workflows.
                        </p>
                    </div>
                </div>

                @if ($notificationDeliveries->isNotEmpty())
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($notificationDeliveries as $delivery)
                            @php
                                $status = $delivery->retries->last()?->status ?? $delivery->status;
                            @endphp
                            <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ str_replace('_', ' ', ucfirst($delivery->notification_type)) }}
                                    </p>
                                    @if ($status === \App\Models\NotificationDelivery::STATUS_FAILED)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $delivery->user_message ?: 'Your email could not be delivered. Please try again later.' }}
                                        </p>
                                    @elseif ($status === \App\Models\NotificationDelivery::STATUS_SUPPRESSED)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $delivery->suppressionReasonLabel() ?? 'Not sent.' }}.
                                        </p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($status === \App\Models\NotificationDelivery::STATUS_DELIVERED)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">Delivered</span>
                                    @elseif ($status === \App\Models\NotificationDelivery::STATUS_SUPPRESSED)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Suppressed</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400">Failed</span>
                                    @endif
                                    <time class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400" datetime="{{ $delivery->created_at?->toIso8601String() }}">
                                        <x-local-time :at="$delivery->created_at" fallback="—" />
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
        </div>
    </section>
@endsection
