{{-- Settings area layout. Extends the global application shell and adds the
     internal Settings sidebar. The global application sidebar is NOT reused. --}}

@extends('layouts.app')

@section('content')
    <div class="mx-auto w-full max-w-6xl">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Settings</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Manage TestOps platform configuration, user management, and access control.
            </p>
        </header>

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-10">
            <aside class="shrink-0 lg:w-56">
                <x-settings.sidebar />
            </aside>

            <div class="min-w-0 flex-1">
                @yield('settings-content')
            </div>
        </div>
    </div>
@endsection
