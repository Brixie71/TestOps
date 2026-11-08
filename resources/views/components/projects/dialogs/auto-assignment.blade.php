@props(['project'])

<x-dialog id="dialog-auto-assignment" context="auto-assignment" title="Automatic assignment" description="Enables or disables automatic Representative Admin ownership assignment. This setting is separate from the current ownership assignment: enabling it alone never assigns or replaces an owner.">
    <form method="POST" action="{{ route('projects.ownership.change-auto-assignment', $project) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="auto-assignment">

        @if ($errors->any() && old('dialog') === 'auto-assignment')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        @php
            $enabled = (string) old('enabled', $project->auto_assign_representative_owner ? '1' : '0');
        @endphp
        <fieldset>
            <legend class="block text-sm font-medium text-slate-700 dark:text-slate-300">Automatic assignment setting</legend>
            <div class="mt-1.5 space-y-1.5">
                <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <input type="radio" name="enabled" value="1" required @checked($enabled === '1') class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600">
                    <span>
                        <span class="font-medium">Enabled</span>
                        <span class="block text-xs text-slate-400 dark:text-slate-500">A newly granted Project Admin on an unowned project automatically becomes the permanent owner.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <input type="radio" name="enabled" value="0" required @checked($enabled === '0') class="mt-0.5 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600">
                    <span>
                        <span class="font-medium">Disabled</span>
                        <span class="block text-xs text-slate-400 dark:text-slate-500">Ownership changes happen only through assignment, transfer, or unassignment.</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <div>
            <label for="auto-assignment-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="auto-assignment-reason" name="reason" required rows="3" maxlength="500" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Update setting</button>
        </div>
    </form>
</x-dialog>
