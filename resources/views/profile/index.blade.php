@extends('layouts.app')

@section('title', 'Profile | TestOps')

@section('content')
    {{-- Minimal profile placeholder. Profile preferences and account details
         are intentionally deferred to a later development stage. --}}

    <section aria-labelledby="profile-heading" class="flex min-h-[60vh] items-center justify-center">
        <div class="max-w-md px-2 text-center">
            <span aria-hidden="true" class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-500">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
            </span>

            <h1 id="profile-heading" class="mt-5 text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                Profile
            </h1>

            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                @if (auth()->user()?->name)
                    Signed in as <span class="font-medium text-slate-700 dark:text-slate-300">{{ auth()->user()->name }}</span>.
                @else
                    You are signed in.
                @endif
                Profile preferences and account details will be managed here in an upcoming development stage.
            </p>
        </div>
    </section>
@endsection
