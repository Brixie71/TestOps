@extends('layouts.settings')

@section('title', ($target->name ?: $target->email).' | TestOps')

@section('settings-content')
    <section aria-labelledby="user-details-heading" class="space-y-6">
        {{-- Back to the directory; preserve nothing — the directory keeps its own state. --}}
        <div>
            <a
                href="{{ route('settings.users') }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Back to users
            </a>
        </div>

        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">
                    {{ mb_strtoupper(mb_substr(trim((string) $target->name), 0, 1)) ?: 'U' }}
                </span>
                <div>
                    <h2 id="user-details-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ $target->name ?: $target->email }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $target->email }}
                    </p>
                </div>
            </div>

            @if ($target->trashed())
                <span class="inline-flex items-center gap-1.5 self-start rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400" aria-hidden="true"></span>
                    Deactivated
                </span>
            @elseif ($target->account_status === 'active')
                <span class="inline-flex items-center gap-1.5 self-start rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Active
                </span>
            @elseif ($target->account_status === 'invited')
                <span class="inline-flex items-center gap-1.5 self-start rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                    Invited
                </span>
            @endif
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Profile, status, and role --}}
            <div class="space-y-6 lg:col-span-1">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Profile</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Contact number</dt>
                            <dd class="text-right font-medium text-slate-700 dark:text-slate-300">{{ $target->contact_number ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Job title</dt>
                            <dd class="text-right font-medium text-slate-700 dark:text-slate-300">{{ $target->job_title ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Department</dt>
                            <dd class="text-right font-medium text-slate-700 dark:text-slate-300">{{ $target->department ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Email verified</dt>
                            <dd class="text-right font-medium text-slate-700 dark:text-slate-300">
                                {{ $target->email_verified_at ? $target->email_verified_at->format('M d, Y') : 'No' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Date created</dt>
                            <dd class="text-right font-medium text-slate-700 dark:text-slate-300">{{ $target->created_at?->format('M d, Y') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if ($canEditProfile)
                        {{-- Administrative profile editing (§3.1, §6.3):
                             only the four profile fields; email, password,
                             role, and account status keep their own flows. --}}
                        <form method="POST" action="{{ route('settings.users.profile.update', $target->getKey()) }}" class="mt-4 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="profile-edit-name" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Name
                                </label>
                                <input
                                    id="profile-edit-name"
                                    type="text"
                                    name="name"
                                    maxlength="255"
                                    autocomplete="name"
                                    value="{{ old('name', $target->name) }}"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                >
                                @error('name')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="profile-edit-contact-number" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Contact number
                                </label>
                                <input
                                    id="profile-edit-contact-number"
                                    type="text"
                                    name="contact_number"
                                    maxlength="30"
                                    autocomplete="tel"
                                    value="{{ old('contact_number', $target->contact_number) }}"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                >
                                @error('contact_number')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="profile-edit-job-title" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Job title
                                </label>
                                <input
                                    id="profile-edit-job-title"
                                    type="text"
                                    name="job_title"
                                    maxlength="100"
                                    value="{{ old('job_title', $target->job_title) }}"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                >
                                @error('job_title')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="profile-edit-department" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Department
                                </label>
                                <input
                                    id="profile-edit-department"
                                    type="text"
                                    name="department"
                                    maxlength="100"
                                    value="{{ old('department', $target->department) }}"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                >
                                @error('department')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    Update profile
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">System role</h3>
                    @if ($target->role?->scope === 'system')
                        <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $target->role->name }}
                            <span class="ml-1 text-xs text-slate-400 dark:text-slate-500">{{ $target->role->code }}</span>
                        </p>
                    @else
                        <p class="mt-2 text-sm text-slate-400 dark:text-slate-500">No system role</p>
                    @endif

                    @if ($canAssignRole)
                        <form method="POST" action="{{ route('settings.users.role.update', $target->getKey()) }}" class="mt-4 space-y-3">
                            @csrf
                            <label for="role-assignment" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Assign system role
                            </label>
                            <select
                                id="role-assignment"
                                name="role_id"
                                class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">No system role</option>
                                @foreach ($assignableRoles as $assignableRole)
                                    <option value="{{ $assignableRole->id }}" @selected((string) $target->role_id === (string) $assignableRole->id)>
                                        {{ $assignableRole->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role_id')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    Update role
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                @if ($canManagePassword)
                    {{-- Administrative password management (§11.2): manual
                         set with the acting admin's own-password confirmation,
                         or a forced single-use email-link reset (§11.3). --}}
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Password management</h3>

                        @if ($errors->any() && ! old('dialog'))
                            <div role="alert" class="mt-3 rounded-lg border border-red-500/25 bg-red-500/10 px-3 py-2.5 text-xs text-red-700 dark:text-red-400">
                                <ul class="list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('settings.users.password.update', $target->getKey()) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <label for="admin-current-password" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Your current password <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input
                                    id="admin-current-password"
                                    type="password"
                                    name="current_password"
                                    required
                                    autocomplete="current-password"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                >
                            </div>
                            <div>
                                <label for="new-user-password" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    New password <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                    At least 12 characters with uppercase, lowercase, number, and symbol.
                                </p>
                                <input
                                    id="new-user-password"
                                    type="password"
                                    name="password"
                                    required
                                    minlength="12"
                                    maxlength="64"
                                    autocomplete="new-password"
                                    class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                >
                            </div>
                            <div>
                                <label for="new-user-password-confirmation" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Confirm new password <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input
                                    id="new-user-password-confirmation"
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
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    Set password
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('settings.users.password.force-reset', $target->getKey()) }}" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                            @csrf
                            <label class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <input
                                    type="checkbox"
                                    name="confirmed"
                                    value="1"
                                    required
                                    class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                >
                                <span>
                                    End this user's sessions and email them a single-use password reset link. Sign-in is blocked until they complete the reset.
                                </span>
                            </label>
                            <div class="mt-3 flex justify-end">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-red-900/70 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-950/40"
                                >
                                    Force email reset
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                @if ($pendingEmailChange !== null)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/70 dark:bg-amber-500/10">
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Pending email change</h3>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                            This account is awaiting verification of
                            <span class="font-medium">{{ $pendingEmailChange->email }}</span>.
                            The current address stays active until the new one is verified.
                        </p>

                        @if ($canCancelEmailChange)
                            <form method="POST" action="{{ route('settings.users.email-change.cancel', $target->getKey()) }}" class="mt-4 space-y-3">
                                @csrf
                                <div>
                                    <label for="email-change-cancel-reason" class="block text-xs font-medium text-amber-800 dark:text-amber-300">
                                        Reason <span class="text-red-500" aria-hidden="true">*</span>
                                    </label>
                                    <textarea
                                        id="email-change-cancel-reason"
                                        name="reason"
                                        rows="2"
                                        required
                                        maxlength="500"
                                        class="mt-1 block w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-amber-900/70 dark:bg-slate-950 dark:text-slate-100"
                                    >{{ old('reason') }}</textarea>
                                    @error('reason')
                                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                    @enderror
                                    @error('email')
                                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-amber-900/70 dark:bg-slate-900 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                    >
                                        Cancel pending email change
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($target->account_status === 'invited' && $latestInvitation !== null && ! $latestInvitation->isCancelled())
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/70 dark:bg-amber-500/10">
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Pending invitation</h3>
                        <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                            An account offer is awaiting acceptance
                            @if ($latestInvitation->expires_at !== null)
                                (expires {{ $latestInvitation->expires_at->format('M d, Y H:i') }}).
                            @else
                                .
                            @endif
                            Resend and cancellation controls are available in the user directory.
                        </p>
                    </div>
                @endif

                @if ($canPermanentlyDelete)
                    {{-- Irreversible account removal (§7.3): Super Admin only,
                         deactivated accounts only, and blocked while a
                         restrictive dependency still references the account. --}}
                    <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-900/70 dark:bg-red-500/10">
                        <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">Permanent deletion</h3>
                        <p class="mt-2 text-sm text-red-700 dark:text-red-400">
                            Permanently deleting this account removes the user record and every email address it
                            owns, revokes its credentials, and ends its sessions. This cannot be undone; audit
                            history keeps its identity snapshots.
                        </p>

                        @if ($deletionDependencies !== [])
                            <div class="mt-3 rounded-lg border border-red-300 bg-white px-3 py-2.5 text-xs text-red-800 dark:border-red-900/70 dark:bg-slate-950 dark:text-red-300">
                                <p class="font-medium">Resolve these dependencies first:</p>
                                <ul class="mt-1 list-inside list-disc space-y-1">
                                    @foreach ($deletionDependencies as $dependency)
                                        <li>
                                            {{ $dependency['label'] }}: {{ $dependency['count'] }}.
                                            {{ $dependency['resolution'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @error('delete')
                            <p class="mt-3 text-xs text-rose-700 dark:text-rose-400">{{ $message }}</p>
                        @enderror

                        <form method="POST" action="{{ route('settings.users.permanent-delete', $target->getKey()) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('DELETE')
                            <div>
                                <label for="permanent-delete-password" class="block text-xs font-medium text-red-800 dark:text-red-300">
                                    Your current password <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input
                                    id="permanent-delete-password"
                                    type="password"
                                    name="current_password"
                                    required
                                    autocomplete="current-password"
                                    class="mt-1 block w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-900/70 dark:bg-slate-950 dark:text-slate-100"
                                >
                                @error('current_password')
                                    <p class="mt-1 text-xs text-rose-700 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="permanent-delete-reason" class="block text-xs font-medium text-red-800 dark:text-red-300">
                                    Reason <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    id="permanent-delete-reason"
                                    name="reason"
                                    rows="2"
                                    required
                                    maxlength="500"
                                    class="mt-1 block w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-900/70 dark:bg-slate-950 dark:text-slate-100"
                                >{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="mt-1 text-xs text-rose-700 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    @disabled($deletionDependencies !== [])
                                    class="inline-flex items-center justify-center rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/70 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-950/40"
                                >
                                    Permanently delete account
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Audit history and notification status --}}
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Audit history</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Latest {{ $auditHistory->count() }} events by or about this account
                        </p>
                    </div>
                    @if ($auditHistory->isNotEmpty())
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($auditHistory as $entry)
                                <li class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                            {{ $entry->action }}
                                            @if ($entry->reason)
                                                <span class="ml-1 text-xs text-slate-400 dark:text-slate-500">— {{ $entry->reason }}</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            By {{ $entry->actor_name ?: 'system' }}
                                        </p>
                                    </div>
                                    <time class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400" datetime="{{ $entry->created_at?->toIso8601String() }}">
                                        {{ $entry->created_at?->format('M d, Y H:i') ?? '—' }}
                                    </time>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            No audit events for this account yet.
                        </p>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent notifications</h3>
                    </div>
                    @if ($deliveries->isNotEmpty())
                        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($deliveries as $delivery)
                                <li class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ $delivery->notification_type }}
                                    </p>
                                    <div class="flex items-center gap-3">
                                        @if ($delivery->status === \App\Models\NotificationDelivery::STATUS_DELIVERED)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">Delivered</span>
                                        @elseif ($delivery->status === \App\Models\NotificationDelivery::STATUS_FAILED)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400">Failed</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">{{ $delivery->status }}</span>
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
                            No notifications sent to this account yet.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
