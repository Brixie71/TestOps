@extends('layouts.settings')

@section('title', 'Notifications | TestOps')

@section('settings-content')
    <section aria-labelledby="notifications-heading" class="space-y-6">
        <header>
            <h2 id="notifications-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Notifications
            </h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                Review the email delivery history and retry failed deliveries. Delivery records are immutable; a retry
                records a separate attempt against the original.
            </p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any() && ! old('dialog'))
            <div role="alert" class="rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            {{-- Filters (GET) --}}
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <form method="GET" action="{{ route('settings.notifications') }}" class="space-y-3">
                    <div class="relative">
                        <label for="notification-search" class="sr-only">Search deliveries by recipient email</label>
                        <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m16 16 4 4" />
                        </svg>
                        <input
                            id="notification-search"
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            maxlength="100"
                            autocomplete="off"
                            placeholder="Search recipient email..."
                            class="block w-full rounded-lg border border-slate-200 bg-white py-2.5 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        >
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <x-settings.filter-select name="status" label="Filter by delivery status" class="w-44">
                            <option value="" @selected(($filters['status'] ?? '') === '')>All statuses</option>
                            <option value="delivered" @selected(($filters['status'] ?? '') === 'delivered')>Delivered</option>
                            <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                            <option value="suppressed" @selected(($filters['status'] ?? '') === 'suppressed')>Suppressed</option>
                        </x-settings.filter-select>

                        <x-settings.filter-select name="type" label="Filter by notification type" class="w-56">
                            <option value="">All types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </x-settings.filter-select>

                        <div class="flex items-center gap-2 sm:ml-auto">
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white">
                                Apply filters
                            </button>

                            @if ($hasFilters)
                                <a href="{{ route('settings.notifications') }}" class="inline-flex items-center justify-center rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Delivery history</p>
                <p class="text-xs text-slate-500 dark:text-slate-400" aria-live="polite">
                    Showing {{ $deliveries->firstItem() ?? 0 }}&ndash;{{ $deliveries->lastItem() ?? 0 }}
                    of {{ $deliveries->total() }} {{ Str::plural('delivery', $deliveries->total()) }}
                </p>
            </div>

            @if ($deliveries->isNotEmpty())
                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <caption class="sr-only">Email delivery history</caption>
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Timestamp</th>
                                <th scope="col" class="px-4 py-3">Recipient</th>
                                <th scope="col" class="px-4 py-3">Type</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Reason</th>
                                <th scope="col" class="px-4 py-3">Retries</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($deliveries as $delivery)
                                @php
                                    $latestRetry = $delivery->retries->last();

                                    // Effective status: the latest retry outcome wins
                                    // over the immutable original status.
                                    $effectiveStatus = $latestRetry?->status ?? $delivery->status;
                                    $attemptCount = $delivery->retries->count();

                                    // Retry controls render only where a resend path
                                    // exists: the retryable types with a linked
                                    // invitation link request. The service still
                                    // enforces the full retry policy on POST.
                                    $canRetry = $delivery->status === \App\Models\NotificationDelivery::STATUS_FAILED
                                        && in_array($delivery->notification_type, $retryableTypes, true)
                                        && $retryableIds->contains($delivery->getKey())
                                        && $attemptCount < $maxRetries
                                        && $latestRetry?->status !== 'delivered';
                                @endphp
                                <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-4 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                        <time datetime="{{ $delivery->created_at?->toIso8601String() }}">
                                            {{ $delivery->created_at?->format('M d, Y H:i') ?? '—' }}
                                        </time>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-slate-700 dark:text-slate-300">{{ $delivery->recipient_email }}</span>
                                        @if ($delivery->user !== null)
                                            <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $delivery->user->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $delivery->notification_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($effectiveStatus === 'delivered')
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">Delivered</span>
                                        @elseif ($effectiveStatus === \App\Models\NotificationDelivery::STATUS_SUPPRESSED)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Suppressed</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400">Failed</span>
                                        @endif
                                        @if ($effectiveStatus !== $delivery->status)
                                            <span class="mt-1 block text-[11px] text-slate-400 dark:text-slate-500">
                                                Original: {{ $delivery->status }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 max-w-[240px] text-slate-600 dark:text-slate-400">
                                        {{ $delivery->suppressionReasonLabel() ?? $latestRetry?->failure_message ?? $delivery->failure_message ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($attemptCount > 0)
                                            <ul class="space-y-1 text-xs text-slate-500 dark:text-slate-400">
                                                @foreach ($delivery->retries as $retry)
                                                    <li class="whitespace-nowrap">
                                                        <span class="font-medium text-slate-700 dark:text-slate-300">Attempt {{ $retry->attempt_number }}</span>
                                                        <span class="{{ $retry->status === 'delivered' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                            {{ $retry->status }}
                                                        </span>
                                                        <span class="block text-[11px]">{{ $retry->attempted_at?->format('M d, H:i') }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-xs text-slate-400 dark:text-slate-500">None</span>
                                        @endif
                                        <span class="mt-1 block text-[11px] text-slate-400 dark:text-slate-500">{{ $attemptCount }} of {{ $maxRetries }} used</span>

                                        @if ($canRetry)
                                            <form method="POST" action="{{ route('settings.notifications.retry', $delivery->getKey()) }}" class="mt-2">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                                                >
                                                    Retry
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="border-t border-slate-200 px-5 py-14 text-center dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                        {{ $hasFilters ? 'No matching deliveries' : 'No deliveries yet' }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        @if ($hasFilters)
                            No email deliveries match the current filters.
                        @else
                            Email delivery records will appear here as notifications are sent.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
