<x-filament-panels::page>
    @php
        $summaryCards = [
            ['label' => 'Expected Guests', 'value' => number_format($totalAllowedGuests), 'icon' => 'heroicon-o-users', 'tone' => 'blue'],
            ['label' => 'Guests Admitted', 'value' => number_format($totalGuestsAdmitted), 'icon' => 'heroicon-o-check-circle', 'tone' => 'green'],
            ['label' => 'Remaining Guests', 'value' => number_format($remainingGuests), 'icon' => 'heroicon-o-clock', 'tone' => 'amber'],
            ['label' => 'Checked-in Invitees', 'value' => number_format($checkedInInvitees), 'icon' => 'heroicon-o-identification', 'tone' => 'purple'],
            ['label' => 'Successful', 'value' => number_format($successfulTransactions), 'icon' => 'heroicon-o-qr-code', 'tone' => 'sky'],
            ['label' => 'Failed Attempts', 'value' => number_format($failedAttempts), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'red'],
            ['label' => 'Partial Check-ins', 'value' => number_format($partialCheckIns), 'icon' => 'heroicon-o-adjustments-horizontal', 'tone' => 'amber'],
            ['label' => 'Check-in Rate', 'value' => number_format($checkInRate, 1).'%', 'icon' => 'heroicon-o-arrow-trending-up', 'tone' => 'blue'],
        ];

        $toneStyles = [
            'blue' => 'background:#EEF2FF;color:#213B73;',
            'green' => 'background:#DCFCE7;color:#15803D;',
            'amber' => 'background:#FFF7ED;color:#FD9618;',
            'purple' => 'background:#F3E8FF;color:#7E22CE;',
            'sky' => 'background:#DBEAFE;color:#1D4ED8;',
            'red' => 'background:#FEE2E2;color:#B91C1C;',
        ];
    @endphp

    <style>
        .checkin-shell{overflow:hidden;border:1px solid #E5E7EB;border-radius:22px;background:#fff;box-shadow:0 10px 25px rgba(15,23,42,.04)}
        .checkin-header{padding:16px 18px;background:#213B73;color:#fff}
        .checkin-summary{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:10px;padding:16px}
        .checkin-card{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #E5E7EB;border-radius:14px;background:#fff}
        .checkin-icon{display:flex;width:38px;height:38px;align-items:center;justify-content:center;border-radius:12px;flex:0 0 38px}
        .checkin-icon svg{width:18px;height:18px}
        .checkin-value{font-size:22px;font-weight:900;line-height:1;color:#111827}
        .checkin-label{margin-top:4px;font-size:11px;font-weight:800;color:#64748B}
        .checkin-progress{padding:0 16px 16px}
        .checkin-progress-box{border:1px solid #E5E7EB;border-radius:14px;padding:14px;background:#F8FAFC}
        .checkin-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:16px;padding:0 16px 16px}
        .checkin-panel{overflow:hidden;border:1px solid #E5E7EB;border-radius:16px;background:#fff}
        .checkin-panel-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #E5E7EB;background:#F8FAFC}
        .checkin-table-wrap{overflow-x:auto}
        .checkin-table{width:100%;min-width:860px;border-collapse:separate;border-spacing:0}
        .checkin-table th{padding:11px 12px;background:#F8FAFC;border-bottom:1px solid #E5E7EB;color:#111827;font-size:11px;font-weight:900;text-align:left;white-space:nowrap}
        .checkin-table td{padding:11px 12px;border-bottom:1px solid #E5E7EB;color:#111827;font-size:12px;vertical-align:middle}
        .checkin-table tbody tr:last-child td{border-bottom:0}
        .checkin-table tbody tr:hover td{background:#F8FAFC}
        .checkin-badge{display:inline-flex;align-items:center;justify-content:center;border:1px solid transparent;border-radius:999px;padding:5px 8px;font-size:11px;font-weight:850;line-height:1;white-space:nowrap}
        .checkin-list{display:flex;flex-direction:column;gap:10px;padding:14px}
        .checkin-list-item{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #E5E7EB;border-radius:12px;padding:11px 12px}
        .checkin-breakdown{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;padding:0 16px 16px}
        .checkin-break-card{border:1px solid #E5E7EB;border-radius:16px;background:#fff;padding:14px}
        .checkin-empty{padding:40px 16px;text-align:center;color:#64748B}
        @media(max-width:1180px){.checkin-summary{grid-template-columns:repeat(4,minmax(0,1fr))}.checkin-grid{grid-template-columns:1fr}.checkin-breakdown{grid-template-columns:1fr}}
        @media(max-width:720px){.checkin-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>

    <div wire:poll.15s class="checkin-shell">
        <div class="checkin-header">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10">
                        <x-filament::icon icon="heroicon-o-qr-code" class="h-5 w-5" />
                    </div>

                    <div>
                        <div class="text-sm font-black">Check-in Dashboard</div>
                        <div class="mt-1 text-xs font-semibold text-white/75">
                            {{ $event->display_name }}
                        </div>
                    </div>
                </div>

                <div class="text-left md:text-right">
                    <div class="text-sm font-black">
                        {{ number_format($totalGuestsAdmitted) }} admitted ·
                        {{ number_format($remainingGuests) }} remaining ·
                        {{ number_format($successfulTransactions) }} transactions
                    </div>
                    <div class="mt-1 text-xs font-semibold text-white/70">
                        {{ number_format($checkInRate, 1) }}% check-in rate
                    </div>
                </div>
            </div>
        </div>

        <div class="checkin-summary">
            @foreach ($summaryCards as $card)
                <div class="checkin-card">
                    <div class="checkin-icon" style="{{ $toneStyles[$card['tone']] }}">
                        <x-filament::icon :icon="$card['icon']" />
                    </div>

                    <div>
                        <div class="checkin-value">{{ $card['value'] }}</div>
                        <div class="checkin-label">{{ $card['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="checkin-progress">
            <div class="checkin-progress-box">
                <div class="flex items-center justify-between text-xs font-bold text-gray-600">
                    <span>{{ number_format($totalGuestsAdmitted) }} admitted</span>
                    <span>{{ number_format($checkInRate, 1) }}%</span>
                    <span>{{ number_format($totalAllowedGuests) }} expected</span>
                </div>

                <div class="mt-3 h-3 overflow-hidden rounded-full bg-gray-200">
                    <div
                        class="h-full rounded-full bg-[#213B73] transition-all duration-500"
                        style="width: {{ min(100, max(0, $checkInRate)) }}%"
                    ></div>
                </div>
            </div>
        </div>

        <div class="checkin-grid">
            <section class="checkin-panel">
                <div class="checkin-panel-head">
                    <div>
                        <div class="text-sm font-black text-gray-900">Recent Check-ins</div>
                        <div class="mt-1 text-xs font-semibold text-gray-500">Latest gate activity</div>
                    </div>

                    <span class="checkin-badge" style="background:#DCFCE7;color:#15803D;border-color:#BBF7D0;">
                        Live
                    </span>
                </div>

                <div class="checkin-table-wrap">
                    <table class="checkin-table">
                        <thead>
                            <tr>
                                <th>Invitee</th>
                                <th>Guests</th>
                                <th>Remaining</th>
                                <th>Method</th>
                                <th>Gate User</th>
                                <th>Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recentCheckIns as $checkIn)
                                @php
                                    $status = (string) ($checkIn->status ?? 'checked_in');
                                    $isFailed = in_array($status, ['failed', 'invalid', 'rejected', 'duplicate'], true);
                                @endphp

                                <tr>
                                    <td>
                                        <div class="font-bold text-gray-900">
                                            {{ $checkIn->invitee?->name ?? 'Unknown invitee' }}
                                        </div>
                                        <div class="mt-1 text-[11px] font-semibold text-gray-500">
                                            {{ $checkIn->invitee?->serial_number ?? 'No serial' }}
                                            @if ($checkIn->invitee?->cardType?->name)
                                                · {{ $checkIn->invitee->cardType->name }}
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        <span class="checkin-badge" style="{{ $isFailed ? 'background:#F1F5F9;color:#64748B;border-color:#E2E8F0;' : 'background:#DCFCE7;color:#15803D;border-color:#BBF7D0;' }}">
                                            {{ (int) $checkIn->guests_checked_in }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="checkin-badge" style="{{ (int) $checkIn->remaining_guests > 0 ? 'background:#FFF7ED;color:#C2410C;border-color:#FED7AA;' : 'background:#F1F5F9;color:#64748B;border-color:#E2E8F0;' }}">
                                            {{ (int) $checkIn->remaining_guests }}
                                        </span>
                                    </td>

                                    <td>{{ str($checkIn->checkin_method ?: 'qr')->replace('_', ' ')->title() }}</td>
                                    <td>{{ $checkIn->checkedInBy?->name ?? 'System' }}</td>
                                    <td>{{ optional($checkIn->checked_in_at)->format('H:i:s') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="checkin-empty">No check-ins yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="checkin-panel">
                <div class="checkin-panel-head">
                    <div>
                        <div class="text-sm font-black text-gray-900">Gate Performance</div>
                        <div class="mt-1 text-xs font-semibold text-gray-500">Entries handled by gate users</div>
                    </div>
                </div>

                <div class="checkin-list">
                    @forelse ($byGateUser as $row)
                        <div class="checkin-list-item">
                            <div>
                                <div class="text-sm font-bold text-gray-900">{{ $row->label }}</div>
                                <div class="mt-1 text-[11px] font-semibold text-gray-500">
                                    {{ number_format((int) $row->transactions) }} transactions
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-xl font-black text-[#213B73]">
                                    {{ number_format((int) $row->guests) }}
                                </div>
                                <div class="text-[10px] font-semibold text-gray-500">guests</div>
                            </div>
                        </div>
                    @empty
                        <div class="checkin-empty">No gate activity yet.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="checkin-breakdown">
            <section class="checkin-break-card">
                <div class="text-sm font-black text-gray-900">Card Types</div>
                <div class="mt-4 space-y-3">
                    @forelse ($byCardType as $row)
                        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-3">
                            <span class="text-sm font-semibold text-gray-700">{{ $row->label }}</span>
                            <span class="checkin-badge" style="background:#EEF2FF;color:#213B73;border-color:#C7D2FE;">
                                {{ number_format((int) $row->total) }}
                            </span>
                        </div>
                    @empty
                        <div class="checkin-empty">No data yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="checkin-break-card">
                <div class="text-sm font-black text-gray-900">Categories</div>
                <div class="mt-4 space-y-3">
                    @forelse ($byCategory as $row)
                        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-3">
                            <span class="text-sm font-semibold text-gray-700">{{ $row->label }}</span>
                            <span class="checkin-badge" style="background:#EEF2FF;color:#213B73;border-color:#C7D2FE;">
                                {{ number_format((int) $row->total) }}
                            </span>
                        </div>
                    @empty
                        <div class="checkin-empty">No data yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="checkin-break-card">
                <div class="text-sm font-black text-gray-900">Tables</div>
                <div class="mt-4 max-h-96 space-y-3 overflow-y-auto pr-1">
                    @forelse ($byTable as $row)
                        @php
                            $expected = max(0, (int) $row->expected);
                            $admitted = max(0, (int) $row->admitted);
                            $percentage = $expected > 0
                                ? min(100, round(($admitted / $expected) * 100))
                                : 0;
                        @endphp

                        <div class="rounded-xl bg-gray-50 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold text-gray-700">{{ $row->label }}</span>
                                <span class="text-xs font-bold text-gray-500">{{ $admitted }}/{{ $expected }}</span>
                            </div>

                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200">
                                <div class="h-full rounded-full bg-[#213B73]" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="checkin-empty">No data yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
