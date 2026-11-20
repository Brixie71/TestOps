<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>TestOps Audit Logs</title>
    <style>
        @page {
            margin: 24px 28px;
        }

        body {
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4px;
        }

        .meta {
            color: #475569;
            margin: 0 0 12px;
            line-height: 1.5;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background: #f1f5f9;
            font-size: 8px;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: top;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        .muted {
            color: #64748b;
        }

        .mono {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8px;
            word-break: break-all;
        }

        .empty {
            color: #64748b;
            padding: 24px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php($activeFilters = collect($filters)->filter(fn ($value): bool => $value !== null && $value !== ''))

    <h1>TestOps Audit Logs</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('M d, Y H:i:s') }}
        &middot; {{ $logs->count() }} {{ $logs->count() === 1 ? 'event' : 'events' }}
        @if ($activeFilters->isNotEmpty())
            <br>
            Filters:
            {{ $activeFilters->map(fn ($value, $key): string => $key.': '.$value)->implode(' | ') }}
        @endif
    </p>

    @if ($logs->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Timestamp (UTC)</th>
                    <th>Action</th>
                    <th>Actor</th>
                    <th>Target</th>
                    <th>Reason</th>
                    <th>Before</th>
                    <th>After</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="mono">{{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="mono">{{ $log->action }}</td>
                        <td>
                            {{ $log->actor_name ?: '—' }}
                            <span class="muted">{{ $log->actor_email }}</span>
                        </td>
                        <td>
                            {{ $log->target_name ?: '—' }}
                            <span class="muted">{{ $log->target_email }}</span>
                        </td>
                        <td>{{ $log->reason ?: '—' }}</td>
                        <td class="mono">{{ $log->before_values !== null ? json_encode($log->before_values) : '—' }}</td>
                        <td class="mono">{{ $log->after_values !== null ? json_encode($log->after_values) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No audit events match the current filters.</p>
    @endif
</body>
</html>
