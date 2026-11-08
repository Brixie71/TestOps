@props(['project', 'eligibleUsers', 'projectRoles'])

<x-dialog id="dialog-add-member" context="add-member" title="Add member" description="Grant an eligible user access to this project.">
    <form method="POST" action="{{ route('projects.members.add', $project) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="add-member">

        @if ($errors->any() && old('dialog') === 'add-member')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="add-member-user" class="block text-sm font-medium text-slate-700 dark:text-slate-300">User</label>
            <select id="add-member-user" name="user_id" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Select a user&hellip;</option>
                @foreach ($eligibleUsers as $candidate)
                    <option value="{{ $candidate->id }}" @selected((string) old('user_id') === (string) $candidate->id)>{{ $candidate->name }} ({{ $candidate->email }})</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Only active accounts that are not yet members are listed.</p>
        </div>

        <div>
            <label for="add-member-role" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Project role</label>
            <select id="add-member-role" name="role_id" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Select a role&hellip;</option>
                @foreach ($projectRoles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="add-member-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="add-member-reason" name="reason" required rows="3" maxlength="500" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Add member</button>
        </div>
    </form>
</x-dialog>
