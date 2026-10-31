<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Worked shifts — {{ $report['driver_name'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { color: #475569; margin-bottom: 16px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        tfoot td { font-weight: bold; background: #f8fafc; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>Worked shifts report</h1>
    <div class="meta">
        <div><strong>Driver:</strong> {{ $report['driver_name'] }} (ID {{ $report['driver_id'] }})</div>
        @if(!empty($report['driver_phone']))
            <div><strong>Phone:</strong> {{ $report['driver_phone'] }}</div>
        @endif
        @if(!empty($report['driver_email']))
            <div><strong>Email:</strong> {{ $report['driver_email'] }}</div>
        @endif
        <div><strong>Period:</strong> {{ $report['date_from'] }} — {{ $report['date_to'] }} ({{ $report['timezone'] }})</div>
        <div><strong>Statuses:</strong> {{ $report['include_booked'] ? 'completed + booked' : 'completed only' }}</div>
        <div class="muted">Generated: {{ $report['generated_at'] }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Start</th>
                <th>End</th>
                <th>Hours</th>
                <th>Station</th>
                <th>Vehicle</th>
                <th>Status</th>
                <th>Bot start</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['shifts'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['start'] }}</td>
                    <td>{{ $row['end'] }}</td>
                    <td>{{ number_format($row['hours'], 2) }}</td>
                    <td>{{ $row['station'] }}</td>
                    <td>{{ $row['vehicle'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['started_via_bot_at'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">No shifts in this period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total ({{ $report['shift_count'] }} shifts)</td>
                <td>{{ number_format($report['total_hours'], 2) }}</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
