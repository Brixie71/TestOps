@extends('layouts.app')

@section('title', 'Dashboard | TestOps')

@section('content')
    {{-- Minimal authenticated landing placeholder.
         Dashboard widgets, analytics, and navigation are intentionally deferred. --}}

    <section aria-labelledby="dashboard-heading" class="flex min-h-[60vh] items-center justify-center">
        <div class="max-w-md px-2 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
            </span>

            <h1 id="dashboard-heading" class="mt-5 text-xl font-semibold tracking-tight text-slate-900">
                Dashboard
            </h1>

            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                @if (auth()->user()?->name)
                    Signed in as <span class="font-medium text-slate-700">{{ auth()->user()->name }}</span>.
                @else
                    You are signed in.
                @endif
                This is the initial authenticated area of TestOps.
            </p>

            <p class="mt-4 text-xs text-slate-400">
                Application modules and navigation will appear here in upcoming development stages.
            </p>
        </div>
    </section>
@endsection
