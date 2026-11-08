@props(['project', 'projectRoles'])

{{-- The {member} route param binds the target user by id: the form action
     is a server-side template; the script replaces __MEMBER__ with the
     member key carried by data-fill="member-key" (restored from
     old('dialog_member') when the dialog reopens after validation errors).
     The target is carried in the URL, not as a posted field. --}}
<x-dialog id="dialog-change-role" context="change-role" title="Change role" description="Updates the member's project-scoped role.">
    <form method="POST" data-action-template="{{ route('projects.members.change-role', ['project' => $project->getKey(), 'member' => '__MEMBER__']) }}" action="{{ route('projects.members.change-role', ['project' => $project->getKey(), 'member' => '__MEMBER__']) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="change-role">
        <input type="hidden" name="dialog_member" data-fill="member-key" value="{{ old('dialog_member') }}">
        <input type="hidden" name="member_name" value="{{ old('member_name') }}">

        @if ($errors->any() && old('dialog') === 'change-role')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <p class="text-sm text-slate-600 dark:text-slate-300">
            Change the project role for <strong data-fill="member-name">{{ old('member_name') }}</strong>.
        </p>

        <div>
            <label for="change-role-role" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Project role</label>
            <select id="change-role-role" name="role_id" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Select a role&hellip;</option>
                @foreach ($projectRoles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="change-role-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="change-role-reason" name="reason" required rows="3" maxlength="500" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Change role</button>
        </div>
    </form>
</x-dialog>
