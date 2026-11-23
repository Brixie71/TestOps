@extends('layouts.settings')

@section('title', 'Roles | TestOps')

@section('settings-content')
    <section aria-labelledby="roles-heading" class="space-y-6">
        <header>
            <h2 id="roles-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Roles
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Role definitions are centrally managed by the Super Admin. The built-in SUPER_ADMIN role is immutable;
                every other role can be renamed and have its permissions reassigned. Role codes and scopes are fixed
                once a role exists. Custom roles can be deleted while no account or project membership references them;
                the built-in SUPER_ADMIN, SYSTEM_ADMIN, and PROJECT_ADMIN roles are permanent.
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

        @if ($editingRole !== null)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                        Edit role
                    </h3>
                    <a href="{{ route('settings.roles') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        Cancel
                    </a>
                </div>

                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <div>
                        <dt class="inline font-medium text-slate-600 dark:text-slate-300">Code:</dt>
                        <dd class="inline font-mono">{{ $editingRole->code }}</dd>
                    </div>
                    <div>
                        <dt class="inline font-medium text-slate-600 dark:text-slate-300">Scope:</dt>
                        <dd class="inline">{{ ucfirst($editingRole->scope) }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('settings.roles.update', $editingRole->getKey()) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="role-edit-name" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Name
                        </label>
                        <input
                            id="role-edit-name"
                            type="text"
                            name="name"
                            maxlength="100"
                            required
                            value="{{ old('name', $editingRole->name) }}"
                            class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >
                        @error('name')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="role-edit-description" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Description
                        </label>
                        <textarea
                            id="role-edit-description"
                            name="description"
                            rows="2"
                            maxlength="255"
                            class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >{{ old('description', $editingRole->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <fieldset>
                        <legend class="text-xs font-medium text-slate-500 dark:text-slate-400">Permissions</legend>
                        <div class="mt-2 grid gap-1.5 sm:grid-cols-2">
                            @forelse ($permissions as $permission)
                                <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-300">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->getKey() }}"
                                        @checked(in_array($permission->getKey(), old('permissions', $editingRole->permissions->modelKeys())))
                                        class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-950"
                                    >
                                    <span>
                                        <span class="font-mono text-xs">{{ $permission->code }}</span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $permission->name }}</span>
                                    </span>
                                </label>
                            @empty
                                <p class="text-xs text-slate-400 dark:text-slate-500">No permissions registered.</p>
                            @endforelse
                        </div>
                        @error('permissions')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                        @error('permissions.*')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-500">
                            Save role
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Create role</h3>

                <form method="POST" action="{{ route('settings.roles.store') }}" class="mt-4 space-y-3">
                    @csrf

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="role-create-name" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Name
                            </label>
                            <input
                                id="role-create-name"
                                type="text"
                                name="name"
                                maxlength="100"
                                required
                                value="{{ old('name') }}"
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                            @error('name')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="role-create-code" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Code
                            </label>
                            <input
                                id="role-create-code"
                                type="text"
                                name="code"
                                maxlength="50"
                                required
                                placeholder="QUALITY_MANAGER"
                                value="{{ old('code') }}"
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                            @error('code')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="role-create-scope" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Scope
                            </label>
                            <select
                                id="role-create-scope"
                                name="scope"
                                required
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="system" @selected(old('scope') === 'system')>System</option>
                                <option value="project" @selected(old('scope') === 'project')>Project</option>
                            </select>
                            @error('scope')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="role-create-description" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                                Description
                            </label>
                            <input
                                id="role-create-description"
                                type="text"
                                name="description"
                                maxlength="255"
                                value="{{ old('description') }}"
                                class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                            >
                            @error('description')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-500">
                            Create role
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Role definitions</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $roles->count() }} {{ Str::plural('role', $roles->count()) }}
                </p>
            </div>

            @if ($roles->isNotEmpty())
                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                    <table class="w-full min-w-[920px] text-left text-sm">
                        <caption class="sr-only">Role definitions and their permissions</caption>
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Role</th>
                                <th scope="col" class="px-4 py-3">Scope</th>
                                <th scope="col" class="px-4 py-3">Permissions</th>
                                <th scope="col" class="px-4 py-3">Description</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($roles as $role)
                                <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-4">
                                        <span class="text-slate-700 dark:text-slate-300">{{ $role->name }}</span>
                                        <span class="block font-mono text-xs text-slate-400 dark:text-slate-500">{{ $role->code }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ ucfirst($role->scope) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($role->permissions->isEmpty())
                                            <span class="text-xs text-slate-400 dark:text-slate-500">None</span>
                                        @else
                                            <div class="flex max-w-[360px] flex-wrap gap-1.5">
                                                @foreach ($role->permissions as $permission)
                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 font-mono text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        {{ $permission->code }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 max-w-[280px] text-slate-600 dark:text-slate-400">
                                        {{ $role->description ?: '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        @if ($role->code === 'SUPER_ADMIN' && $role->scope === 'system')
                                            <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-300">
                                                Protected
                                            </span>
                                        @else
                                            <div class="flex items-center justify-end gap-3">
                                                <a
                                                    href="{{ route('settings.roles', ['edit' => $role->getKey()]) }}"
                                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                                >
                                                    Edit
                                                </a>

                                                @if (in_array($role->code, ['SUPER_ADMIN', 'SYSTEM_ADMIN', 'PROJECT_ADMIN'], true))
                                                    <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-300">
                                                        Built-in
                                                    </span>
                                                @elseif ($role->users_count > 0 || $role->project_members_count > 0)
                                                    <span
                                                        class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                        title="Assigned to {{ $role->users_count }} {{ Str::plural('account', $role->users_count) }} and {{ $role->project_members_count }} project {{ Str::plural('membership', $role->project_members_count) }}. Reassign or remove them before deleting it."
                                                    >
                                                        In use
                                                    </span>
                                                @else
                                                    <form
                                                        method="POST"
                                                        action="{{ route('settings.roles.destroy', $role->getKey()) }}"
                                                        onsubmit="return confirm('Delete this role? This cannot be undone.');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="text-xs font-medium text-rose-600 transition hover:text-rose-500 dark:text-rose-400"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="border-t border-slate-200 px-5 py-14 text-center dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">No roles defined</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Role definitions will appear here once seeded.</p>
                </div>
            @endif
        </div>
    </section>
@endsection
