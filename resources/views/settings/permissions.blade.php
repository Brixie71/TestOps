@extends('layouts.settings')

@section('title', 'Permissions | TestOps')

@section('settings-content')
    <section aria-labelledby="permissions-heading" class="space-y-6">
        <header>
            <h2 id="permissions-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Permissions
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Permissions are assigned to roles by the Super Admin. Codes are referenced directly by authorization
                checks, so they are fixed: only the display name and description can be edited. Permissions cannot be
                created or deleted here.
            </p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
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

        @if ($editingPermission !== null)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                        Edit permission
                    </h3>
                    <a href="{{ route('settings.permissions') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        Cancel
                    </a>
                </div>

                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <div>
                        <dt class="inline font-medium text-slate-600 dark:text-slate-300">Code:</dt>
                        <dd class="inline font-mono">{{ $editingPermission->code }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('settings.permissions.update', $editingPermission->getKey()) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="permission-edit-name" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Name
                        </label>
                        <input
                            id="permission-edit-name"
                            type="text"
                            name="name"
                            maxlength="100"
                            required
                            value="{{ old('name', $editingPermission->name) }}"
                            class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >
                        @error('name')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="permission-edit-description" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Description
                        </label>
                        <textarea
                            id="permission-edit-description"
                            name="description"
                            rows="2"
                            maxlength="255"
                            class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >{{ old('description', $editingPermission->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-500">
                            Save permission
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Registered permissions</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $permissions->count() }} {{ Str::plural('permission', $permissions->count()) }}
                </p>
            </div>

            @if ($permissions->isNotEmpty())
                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <caption class="sr-only">Registered permissions and the roles that grant them</caption>
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Permission</th>
                                <th scope="col" class="px-4 py-3">Description</th>
                                <th scope="col" class="px-4 py-3">Granted by</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($permissions as $permission)
                                <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-4">
                                        <span class="text-slate-700 dark:text-slate-300">{{ $permission->name }}</span>
                                        <span class="block font-mono text-xs text-slate-400 dark:text-slate-500">{{ $permission->code }}</span>
                                    </td>
                                    <td class="px-4 py-4 max-w-[320px] text-slate-600 dark:text-slate-400">
                                        {{ $permission->description ?: '—' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($permission->roles->isEmpty())
                                            <span class="text-xs text-slate-400 dark:text-slate-500">No roles</span>
                                        @else
                                            <div class="flex max-w-[360px] flex-wrap gap-1.5">
                                                @foreach ($permission->roles as $role)
                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ $role->code }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <a
                                            href="{{ route('settings.permissions', ['edit' => $permission->getKey()]) }}"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                        >
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="border-t border-slate-200 px-5 py-14 text-center dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">No permissions defined</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Permissions will appear here once seeded.</p>
                </div>
            @endif
        </div>
    </section>
@endsection
