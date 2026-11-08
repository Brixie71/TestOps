{{-- Settings area layout. Extends the global application shell and adds the
     internal Settings sidebar. The global application sidebar is NOT reused.

     No inner max-width on purpose: the app shell already constrains content
     (max-w-7xl). Nesting a second cap here starves wide tables like the
     User Directory and forces needless horizontal scrolling. --}}

@extends('layouts.app')

@section('content')
    <header class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Settings</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            Manage TestOps platform configuration, user management, and access control.
        </p>
    </header>

    <div class="flex flex-col gap-8 lg:flex-row lg:gap-8">
        <aside class="shrink-0 lg:sticky lg:top-24 lg:self-start lg:w-52">
            <x-settings.sidebar />
        </aside>

        <div class="min-w-0 flex-1">
            @yield('settings-content')
        </div>
    </div>
@endsection
