@props(['project'])

<x-dialog id="dialog-unassign-owner" context="unassign-owner" title="Unassign owner" description="Removes the designated administrator from this project.">
    <form method="POST" action="{{ route('projects.ownership.unassign', $project) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="dialog" value="unassign-owner">

        @if ($errors->any() && old('dialog') === 'unassign-owner')
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <p class="text-sm text-slate-600 dark:text-slate-300">
            Unassign <strong>{{ $project->owner?->name ?? 'the current owner' }}</strong> as the project's designated administrator?
        </p>

        <p class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:border-red-500/40 dark:text-red-400">
            The project will show no designated administrator until a new owner is assigned by ownership assignment or transfer.
        </p>

        <div>
            <label for="unassign-owner-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reason <span class="text-red-500" aria-hidden="true">*</span></label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Recorded in the project audit log.</p>
            <textarea id="unassign-owner-reason" name="reason" required rows="3" maxlength="500" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ old('reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-2.5 pt-1">
            <button type="button" data-dialog-close class="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Unassign owner</button>
        </div>
    </form>
</x-dialog>
