@extends('layouts.app')

@section('title', 'Dashboard | TestOps')

@section('content')
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A read-only summary of the work you can access.</p>
        </header>

        <section aria-label="Summary metrics" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($metrics as $metric)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ number_format($metric['value']) }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $metric['description'] }}</p>
                </div>
            @endforeach
        </section>
    </div>
@endsection
