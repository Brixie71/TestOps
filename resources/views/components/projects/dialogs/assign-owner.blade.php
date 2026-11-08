@props(['project', 'eligibleOwners'])

<x-dialog id="dialog-assign-owner" context="assign-owner" title="Assign owner" description="Designates the project's first administrator.">
    <form method="POST" action="{{ route('projects.ownership.assign', $project) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="assign-owner">

        @if ($errors->any() && old('dialog') === 'assign-owner')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label for="assign-owner-user" class="block text-sm font-medium text-slate-700 dark:text-slate-300">User</label>
            <select id="assign-owner-user" name="user_id" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Select a user&hellip;</option>
                @foreach ($eligibleOwners as $candidate)
                    <option value="{{ $candidate->id }}" @selected((string) old('user_id') === (string) $candidate->id)>{{ $candidate->name }} ({{ $candidate->email }})</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Only active members with the Project Admin role are eligible.</p>
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-slate-700 dark:text-slate-300">Assignment type</legend>
            <div class="mt-1.5 space-y-1.5">
                <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <input type="radio" name="assignment_type" value="permanent" required @checked(old('assignment_type', 'permanent') === 'permanent') class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600">
                    <span>
                        <span class="font-medium">Permanent</span>
                        <span class="block text-xs text-slate-400 dark:text-slate-500">Assigned Administrator — remains until transferred or unassigned.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <input type="radio" name="assignment_type" value="temporary" required @checked(old('assignment_type', 'permanent') === 'temporary') class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600">
                    <span>
                        <span class="font-medium">Temporary</span>
                        <span class="block text-xs text-slate-400 dark:text-slate-500">Temporary Admin — remains in effect until changed.</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <div>
            <label for="assign-owner-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="assign-owner-reason" name="reason" required rows="3" maxlength="500" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Assign owner</button>
        </div>
    </form>
</x-dialog>
