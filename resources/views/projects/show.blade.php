@extends('layouts.app')

@section('title', $project->name.' | TestOps')

@section('content')
    @php
        $owner = $project->owner;
        $ownerDeactivated = $owner && method_exists($owner, 'trashed') && $owner->trashed();
        $can = $can ?? [];
    @endphp

    <div class="mx-auto w-full max-w-6xl space-y-8">
        <a
            href="{{ route('projects.index') }}"
            class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-slate-500 transition-colors hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:text-slate-200"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Projects
        </a>

        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $project->name }}</h1>
                <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $project->code }}</span>
                <x-status-badge :status="$project->status" />
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any() && ! old('dialog'))
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- A. Project overview --}}
        <section aria-labelledby="overview-heading">
            <h2 id="overview-heading" class="text-base font-semibold text-slate-900 dark:text-white">Overview</h2>
            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Description</dt>
                        <dd class="mt-1.5 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $project->description ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Designated administrator</dt>
                        <dd class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            @if ($owner)
                                <span aria-hidden="true" class="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">{{ mb_strtoupper(mb_substr(trim((string) $owner->name), 0, 1)) ?: 'U' }}</span>
                                {{ $owner->name }}
                                @if ($ownerDeactivated)
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400">Deactivated</span>
                                @endif
                            @else
                                <span class="text-slate-400 dark:text-slate-500">No admin assigned</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Ownership assignment</dt>
                        <dd class="mt-1.5">
                            @if ($owner && ! $ownerDeactivated)
                                <x-projects.ownership-badge :state="$project->owner_assignment_type" />
                            @elseif ($ownerDeactivated)
                                <span class="text-sm text-slate-500 dark:text-slate-400">Retained — owner is deactivated</span>
                            @else
                                <x-projects.ownership-badge state="none" />
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Automatic owner assignment</dt>
                        <dd class="mt-1.5 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ ($project->auto_assign_representative_owner) ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400' : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400' }}">
                                {{ ($project->auto_assign_representative_owner) ? 'Enabled' : 'Disabled' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- B. Project members --}}
        <section aria-labelledby="members-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="members-heading" class="text-base font-semibold text-slate-900 dark:text-white">Members</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">People assigned to this project and their project roles.</p>
                </div>
                @if ($can['add_member'] ?? false)
                    <button type="button" data-dialog-target="dialog-add-member" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Add member
                    </button>
                @endif
            </div>

            <div class="mt-3 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @if ($members->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <caption class="sr-only">Project members</caption>
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Member</th>
                                    <th scope="col" class="px-5 py-3">Email</th>
                                    <th scope="col" class="px-5 py-3">Job title</th>
                                    <th scope="col" class="px-5 py-3">Project role</th>
                                    @if (($can['change_member_role'] ?? false) || ($can['remove_member'] ?? false))
                                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($members as $member)
                                    @php
                                        // Pivot exposes role_id; the project-roles map is
                                        // keyed by id and provided by the controller.
                                        $roleName = $projectRoles->get($member->pivot->role_id)?->name ?? '—';
                                        $jobTitle = $member->job_title ?? $member->department ?? null;
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-3">
                                                <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">{{ mb_strtoupper(mb_substr(trim((string) $member->name), 0, 1)) ?: 'U' }}</span>
                                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $member->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ $member->email }}</td>
                                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ $jobTitle ?? '—' }}</td>
                                        <td class="px-5 py-4 font-medium text-slate-700 dark:text-slate-300">{{ $roleName }}</td>
                                        @if (($can['change_member_role'] ?? false) || ($can['remove_member'] ?? false))
                                            <td class="px-5 py-4">
                                                <div class="flex justify-end gap-1">
                                                    @if ($can['change_member_role'] ?? false)
                                                        <button type="button" data-dialog-target="dialog-change-role" data-member-key="{{ $member->id }}" data-member-name="{{ $member->name }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-600 transition-colors hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-500/10">Change role</button>
                                                    @endif
                                                    @if ($can['remove_member'] ?? false)
                                                        <button type="button" data-dialog-target="dialog-remove-member" data-member-key="{{ $member->id }}" data-member-name="{{ $member->name }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-red-400 dark:hover:bg-red-500/10">Remove</button>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($members->hasPages())
                        <div class="border-t border-slate-200 dark:border-slate-800">
                            <x-pagination :paginator="$members" label="members" />
                        </div>
                    @endif
                @else
                    <div class="border-t border-slate-200 dark:border-slate-800">
                        <x-empty-state heading="No members" description="Members assigned to this project will appear here." />
                    </div>
                @endif
            </div>
        </section>

        {{-- C + D. Ownership information and management actions --}}
        <section aria-labelledby="ownership-heading">
            <h2 id="ownership-heading" class="text-base font-semibold text-slate-900 dark:text-white">Ownership</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Designated administrator, assignment type, and automatic assignment setting.</p>

            <div class="mt-3 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        @if ($owner)
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr(trim((string) $owner->name), 0, 1)) ?: 'U' }}</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $owner->name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $ownerDeactivated ? 'Deactivated account' : $owner->email }}</p>
                            </div>
                        @else
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">No admin assigned</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">This project currently has no designated administrator.</p>
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($owner && ! $ownerDeactivated)
                            <x-projects.ownership-badge :state="$project->owner_assignment_type" />
                        @elseif ($ownerDeactivated)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:border-amber-900/70 dark:bg-amber-500/10 dark:text-amber-400">Deactivated</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                            Auto-assignment: {{ ($project->auto_assign_representative_owner) ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 px-5 py-4">
                    @if (! $owner && ($can['assign_owner'] ?? false))
                        <button type="button" data-dialog-target="dialog-assign-owner" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Assign owner</button>
                    @endif
                    @if ($can['transfer_owner'] ?? false)
                        <button type="button" data-dialog-target="dialog-transfer-owner" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Transfer owner</button>
                    @endif
                    @if ($owner && ! $ownerDeactivated && ($can['change_assignment_type'] ?? false))
                        <button type="button" data-dialog-target="dialog-assignment-type" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Change assignment type</button>
                    @endif
                    @if ($can['toggle_auto_assignment'] ?? false)
                        <button type="button" data-dialog-target="dialog-auto-assignment" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Automatic assignment&hellip;</button>
                    @endif
                    @if ($owner && ($can['unassign_owner'] ?? false))
                        <button type="button" data-dialog-target="dialog-unassign-owner" class="inline-flex items-center rounded-lg border border-red-200 px-3.5 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-red-500/40 dark:text-red-400 dark:hover:bg-red-500/10">Unassign owner</button>
                    @endif
                </div>
            </div>

            @if ($owner && $project->owner_assignment_type === 'temporary')
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                    Temporary ownership remains in effect until changed. There is no automatic expiration.
                </p>
            @endif
        </section>
    </div>

    {{-- ================================================================
         Mutation dialogs — all POST to existing backend routes.
         Field names match the backend contract: user_id, role_id,
         assignment_type, enabled, reason (max 500). Change-role and
         remove-member carry the target as the {member} route param, so
         their forms use a server-side URL template rewritten by the
         script. All dialogs stay in the DOM and are CSS-hidden.
    ================================================================= --}}

    @if ($can['add_member'] ?? false)
        <x-projects.dialogs.add-member :project="$project" :eligible-users="$eligibleUsers" :project-roles="$projectRoles" />
    @endif
    @if ($can['change_member_role'] ?? false)
        <x-projects.dialogs.change-role :project="$project" :project-roles="$projectRoles" />
    @endif
    @if ($can['remove_member'] ?? false)
        <x-projects.dialogs.remove-member :project="$project" />
    @endif
    @if ($can['assign_owner'] ?? false)
        <x-projects.dialogs.assign-owner :project="$project" :eligible-owners="$eligibleOwners" />
    @endif
    @if ($can['transfer_owner'] ?? false)
        <x-projects.dialogs.transfer-owner :project="$project" :eligible-owners="$eligibleOwners" />
    @endif
    @if ($can['unassign_owner'] ?? false)
        <x-projects.dialogs.unassign-owner :project="$project" />
    @endif
    @if ($can['change_assignment_type'] ?? false)
        <x-projects.dialogs.assignment-type :project="$project" />
    @endif
    @if ($can['toggle_auto_assignment'] ?? false)
        <x-projects.dialogs.auto-assignment :project="$project" />
    @endif

        <script>
            (function () {
                var lastTrigger = null;

                // Server-side URL template forms (change-role/remove-member)
                // post to the {member} route: the template (including the
                // literal __MEMBER__ placeholder) is generated in Blade, and
                // the script replaces the placeholder with the member key.
                // After a validation failure the dialog reopens server-side
                // and the key is restored from old('dialog_member').
                var MEMBER_PLACEHOLDER = '__MEMBER__';

                function rewriteAction(root, memberKey) {
                    root.querySelectorAll('form[data-action-template]').forEach(function (form) {
                        var field = root.querySelector('[data-fill="member-key"]');
                        var key = memberKey !== undefined && memberKey !== null && memberKey !== ''
                            ? memberKey
                            : (field ? field.value : '');
                        form.setAttribute('action', form.getAttribute('data-action-template').replace(MEMBER_PLACEHOLDER, key));
                    });
                }

                function openDialog(root, trigger) {
                    lastTrigger = trigger || null;
                    root.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';

                    // Populate per-member context from trigger data attributes.
                    if (trigger) {
                        root.querySelectorAll('[data-fill="member-key"]').forEach(function (el) { el.value = trigger.dataset.memberKey || ''; });
                        root.querySelectorAll('[data-fill="member-name"]').forEach(function (el) { el.textContent = trigger.dataset.memberName || ''; });
                        rewriteAction(root, trigger.dataset.memberKey);
                    }

                    var focusable = root.querySelector('input:not([type=hidden]), select, textarea, button:not([data-dialog-close])');
                    (focusable || root.querySelector('button')).focus();
                }

                function closeDialog(root) {
                    root.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (lastTrigger) { lastTrigger.focus(); lastTrigger = null; }
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-dialog-target]');
                    if (trigger) {
                        var root = document.getElementById(trigger.getAttribute('data-dialog-target'));
                        if (root) { openDialog(root, trigger); }
                        return;
                    }
                    if (event.target.closest('[data-dialog-close]')) {
                        var open = event.target.closest('[role="dialog"]');
                        if (open) { closeDialog(open); }
                    }
                });

                document.addEventListener('keydown', function (event) {
                    var open = document.querySelector('[role="dialog"]:not(.hidden)');
                    if (! open) { return; }

                    if (event.key === 'Escape') { closeDialog(open); return; }

                    if (event.key === 'Tab') {
                        var focusables = Array.prototype.filter.call(
                            open.querySelectorAll('button, input, select, textarea, a[href]'),
                            function (el) { return ! el.disabled; }
                        );
                        if (focusables.length === 0) { return; }
                        var first = focusables[0];
                        var last = focusables[focusables.length - 1];
                        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                        else if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                    }
                });

                // Server-rendered reopen after validation failure: lock scroll
                // immediately and restore the member-key action template.
                var rendered = document.querySelector('[role="dialog"]:not(.hidden)');
                if (rendered) {
                    rewriteAction(rendered);
                    document.body.style.overflow = 'hidden';
                }
            })();
        </script>
@endsection
