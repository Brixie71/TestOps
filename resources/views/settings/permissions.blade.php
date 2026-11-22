@extends('layouts.settings')

@section('title', 'Permissions | TestOps')

@section('settings-content')
    <section aria-labelledby="permissions-heading" class="space-y-6">
        <header>
            <h2 id="permissions-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Permissions
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Permissions are assigned to roles by the Super Admin. This overview is read-only until permission
                editing is approved.
            </p>
        </header>

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
