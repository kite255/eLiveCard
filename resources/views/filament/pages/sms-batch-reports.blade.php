<x-filament-panels::page>
    @php
        $reports = $this->batchReports;

        $totalBatches = $reports->count();
        $totalSms = $reports->sum('total_sms');
        $totalSent = $reports->sum('sent_count') + $reports->sum('delivered_count');
        $totalFailed = $reports->sum('failed_count');
        $totalPending = $reports->sum('pending_count');
    @endphp

    <div style="display: grid; gap: 28px;">
        {{-- Summary Cards --}}
        <div style="
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            max-width: 1220px;
        ">
            <div style="background:#18181b;border:1px solid #27272a;border-radius:14px;padding:24px;">
                <div style="color:#cbd5e1;font-size:14px;font-weight:600;">Total Batches</div>
                <div style="margin-top:12px;color:white;font-size:34px;font-weight:800;line-height:1;">
                    {{ number_format($totalBatches) }}
                </div>
                <div style="margin-top:14px;color:#f59e0b;font-size:14px;font-weight:600;">
                    Grouped by Batch ID
                </div>
            </div>

            <div style="background:#18181b;border:1px solid #27272a;border-radius:14px;padding:24px;">
                <div style="color:#cbd5e1;font-size:14px;font-weight:600;">Total SMS</div>
                <div style="margin-top:12px;color:white;font-size:34px;font-weight:800;line-height:1;">
                    {{ number_format($totalSms) }}
                </div>
                <div style="margin-top:14px;color:#3b82f6;font-size:14px;font-weight:600;">
                    All SMS attempts
                </div>
            </div>

            <div style="background:#18181b;border:1px solid #27272a;border-radius:14px;padding:24px;">
                <div style="color:#cbd5e1;font-size:14px;font-weight:600;">Sent SMS</div>
                <div style="margin-top:12px;color:white;font-size:34px;font-weight:800;line-height:1;">
                    {{ number_format($totalSent) }}
                </div>
                <div style="margin-top:14px;color:#22c55e;font-size:14px;font-weight:600;">
                    Successfully submitted
                </div>
            </div>

            <div style="background:#18181b;border:1px solid #27272a;border-radius:14px;padding:24px;">
                <div style="color:#cbd5e1;font-size:14px;font-weight:600;">Failed SMS</div>
                <div style="margin-top:12px;color:white;font-size:34px;font-weight:800;line-height:1;">
                    {{ number_format($totalFailed) }}
                </div>
                <div style="margin-top:14px;color:#ef4444;font-size:14px;font-weight:600;">
                    Needs attention
                </div>
            </div>

            <div style="background:#18181b;border:1px solid #27272a;border-radius:14px;padding:24px;">
                <div style="color:#cbd5e1;font-size:14px;font-weight:600;">Pending SMS</div>
                <div style="margin-top:12px;color:white;font-size:34px;font-weight:800;line-height:1;">
                    {{ number_format($totalPending) }}
                </div>
                <div style="margin-top:14px;color:#f59e0b;font-size:14px;font-weight:600;">
                    Waiting for provider response
                </div>
            </div>

            <div style="background:#18181b;border:1px solid #27272a;border-radius:14px;padding:24px;">
                <div style="color:#cbd5e1;font-size:14px;font-weight:600;">Success Rate</div>
                <div style="margin-top:12px;color:white;font-size:34px;font-weight:800;line-height:1;">
                    {{ $totalSms > 0 ? round(($totalSent / $totalSms) * 100) : 0 }}%
                </div>
                <div style="margin-top:14px;color:#22c55e;font-size:14px;font-weight:600;">
                    Overall delivery attempt success
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div style="
            max-width: 1220px;
            background:#18181b;
            border:1px solid #27272a;
            border-radius:14px;
            overflow:hidden;
        ">
            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                padding:18px 24px;
                border-bottom:1px solid #27272a;
            ">
                <div>
                    <h2 style="margin:0;color:white;font-size:20px;font-weight:800;">
                        SMS Batch Reports
                    </h2>
                    <p style="margin:6px 0 0;color:#94a3b8;font-size:14px;">
                        Each row represents one SMS sending operation grouped by Batch ID.
                    </p>
                </div>

                <div style="
                    color:#f59e0b;
                    background:rgba(245,158,11,.12);
                    border-radius:999px;
                    padding:7px 12px;
                    font-size:12px;
                    font-weight:700;
                    white-space:nowrap;
                ">
                    Latest 100 batches
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="background:#27272a;color:white;">
                            <th style="padding:14px 16px;text-align:left;font-weight:700;min-width:160px;">Batch ID</th>
                            <th style="padding:14px 16px;text-align:left;font-weight:700;min-width:180px;">Event</th>
                            <th style="padding:14px 16px;text-align:left;font-weight:700;min-width:145px;">SMS Type</th>
                            <th style="padding:14px 16px;text-align:left;font-weight:700;min-width:100px;">Source</th>
                            <th style="padding:14px 16px;text-align:left;font-weight:700;min-width:110px;">Sent By</th>
                            <th style="padding:14px 16px;text-align:right;font-weight:700;">Total</th>
                            <th style="padding:14px 16px;text-align:right;font-weight:700;">Sent</th>
                            <th style="padding:14px 16px;text-align:right;font-weight:700;">Failed</th>
                            <th style="padding:14px 16px;text-align:right;font-weight:700;">Pending</th>
                            <th style="padding:14px 16px;text-align:right;font-weight:700;">Success</th>
                            <th style="padding:14px 16px;text-align:left;font-weight:700;min-width:120px;">Started</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($reports as $report)
                            <tr style="border-top:1px solid #27272a;">
                                <td style="padding:14px 16px;color:white;">
                                    <div style="font-family:monospace;font-size:12px;font-weight:700;">
                                        {{ \Illuminate\Support\Str::limit($report->batch_id, 18) }}
                                    </div>
                                    <div style="margin-top:4px;color:#94a3b8;font-size:12px;">
                                        Logs #{{ $report->first_log_id }} - #{{ $report->last_log_id }}
                                    </div>
                                </td>

                                <td style="padding:14px 16px;color:white;font-weight:700;">
                                    {{ $report->event_title ?: 'No Event' }}
                                </td>

                                <td style="padding:14px 16px;">
                                    <span style="
                                        display:inline-flex;
                                        align-items:center;
                                        white-space:nowrap;
                                        background:rgba(245,158,11,.15);
                                        color:#f59e0b;
                                        border:1px solid rgba(245,158,11,.35);
                                        padding:5px 10px;
                                        border-radius:7px;
                                        font-size:12px;
                                        font-weight:700;
                                    ">
                                        {{ $this->formatSmsType($report->sms_type) }}
                                    </span>
                                </td>

                                <td style="padding:14px 16px;">
                                    <span style="
                                        display:inline-flex;
                                        align-items:center;
                                        white-space:nowrap;
                                        background:#27272a;
                                        color:#d4d4d8;
                                        border:1px solid #3f3f46;
                                        padding:5px 9px;
                                        border-radius:7px;
                                        font-size:12px;
                                        font-weight:600;
                                    ">
                                        {{ $this->formatSource($report->send_source) }}
                                    </span>
                                </td>

                                <td style="padding:14px 16px;color:#94a3b8;">
                                    {{ $report->sent_by }}
                                </td>

                                <td style="padding:14px 16px;text-align:right;color:white;font-weight:700;">
                                    {{ number_format($report->total_sms) }}
                                </td>

                                <td style="padding:14px 16px;text-align:right;color:#22c55e;font-weight:700;">
                                    {{ number_format($report->sent_count + $report->delivered_count) }}
                                </td>

                                <td style="padding:14px 16px;text-align:right;color:#ef4444;font-weight:700;">
                                    {{ number_format($report->failed_count) }}
                                </td>

                                <td style="padding:14px 16px;text-align:right;color:#f59e0b;font-weight:700;">
                                    {{ number_format($report->pending_count) }}
                                </td>

                                <td style="padding:14px 16px;text-align:right;color:white;font-weight:700;">
                                    {{ $this->successRate($report) }}
                                </td>

                                <td style="padding:14px 16px;color:#52525b;">
                                    {{ $report->started_at ? \Carbon\Carbon::parse($report->started_at)->format('d M Y H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" style="padding:40px 16px;text-align:center;color:#94a3b8;">
                                    No SMS batch reports found yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>