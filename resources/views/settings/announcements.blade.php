@extends('layouts.settings')

@section('title', 'Announcements | TestOps')

@section('settings-content')
    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div class="max-w-2xl">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Send a system announcement</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Delivered by email to every active account. Recipients who disabled system announcements are
                        recorded as suppressed and receive nothing. Each active account gets one delivery row in the
                        email history.
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    {{ $activeRecipientCount }} active {{ \Illuminate\Support\Str::plural('account', $activeRecipientCount) }}
                </span>
            </div>

            <div class="px-5 py-5 sm:px-6">
                @if (session('status'))
                    <div role="status" class="mb-4 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="mb-4 rounded-xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.announcements.send') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="announcement-subject" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Subject <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="announcement-subject"
                            type="text"
                            name="subject"
                            required
                            maxlength="150"
                            value="{{ old('subject') }}"
                            placeholder="Scheduled maintenance on Saturday"
                            class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        >
                        @error('subject')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="announcement-body" class="block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Message <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            id="announcement-body"
                            name="body"
                            rows="8"
                            required
                            maxlength="5000"
                            placeholder="Describe the maintenance window or release notes. Line breaks are preserved in the email."
                            class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        >{{ old('body') }}</textarea>
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                            Sent as plain text. The email includes a link to the recipient's profile so they can
                            change their notification preferences.
                        </p>
                        @error('body')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                        >
                            Send announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Recent announcements</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    The latest 10 per-recipient delivery outcomes. Complete history is available under Notifications.
                </p>
            </div>

            @if ($recentDeliveries->isNotEmpty())
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($recentDeliveries as $delivery)
                        <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $delivery->recipient_email }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    @if ($delivery->status === \App\Models\NotificationDelivery::STATUS_SUPPRESSED)
                                        {{ $delivery->suppressionReasonLabel() ?? 'Not sent.' }}.
                                    @elseif ($delivery->status === \App\Models\NotificationDelivery::STATUS_FAILED)
                                        {{ $delivery->failure_message ?: 'Delivery failed.' }}
                                    @else
                                        Email delivered.
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                @if ($delivery->status === \App\Models\NotificationDelivery::STATUS_DELIVERED)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-500/10 dark:text-emerald-400">Delivered</span>
                                @elseif ($delivery->status === \App\Models\NotificationDelivery::STATUS_SUPPRESSED)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Suppressed</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:border-red-900/70 dark:bg-red-500/10 dark:text-red-400">Failed</span>
                                @endif
                                <time class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400" datetime="{{ $delivery->created_at?->toIso8601String() }}">
                                    <x-local-time :at="$delivery->created_at" fallback="—" />
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    No announcements sent yet.
                </p>
            @endif
        </div>
    </div>
@endsection
