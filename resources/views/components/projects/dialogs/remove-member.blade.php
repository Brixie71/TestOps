@props(['project'])

{{-- The {member} route param binds the target user by id: the form action
     is a server-side template; the script replaces __MEMBER__ with the
     member key carried by data-fill="member-key" (restored from
     old('dialog_member') when the dialog reopens after validation errors).
     The target is carried in the URL, not as a posted field. --}}
<x-dialog id="dialog-remove-member" context="remove-member" title="Remove member" description="Revokes this member's access to the project.">
    <form method="POST" data-action-template="{{ route('projects.members.remove', ['project' => $project->getKey(), 'member' => '__MEMBER__']) }}" action="{{ route('projects.members.remove', ['project' => $project->getKey(), 'member' => '__MEMBER__']) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="remove-member">
        <input type="hidden" name="dialog_member" data-fill="member-key" value="{{ old('dialog_member') }}">
        <input type="hidden" name="member_name" value="{{ old('member_name') }}">

        @if ($errors->any() && old('dialog') === 'remove-member')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <p class="text-sm text-slate-600 dark:text-slate-300">
            Remove <strong data-fill="member-name">{{ old('member_name') }}</strong> from this project?
        </p>

        <p class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:text-red-400">
            This action is immediate: the member loses project access and any project-scoped role.
        </p>

        <div>
            <label for="remove-member-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="remove-member-reason" name="reason" required rows="3" maxlength="500" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Remove member</button>
        </div>
    </form>
</x-dialog>
