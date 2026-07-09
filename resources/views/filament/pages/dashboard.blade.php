<x-filament-panels::page>
    @php
        $attendingPercent = $totalInvitees > 0 ? round(($attending / $totalInvitees) * 100) : 0;
        $notAttendingPercent = $totalInvitees > 0 ? round(($notAttending / $totalInvitees) * 100) : 0;
        $pendingPercent = $totalInvitees > 0 ? round(($rsvpPending / $totalInvitees) * 100) : 0;
        $checkedInPercent = $totalInvitees > 0 ? round(($checkedInInvitees / $totalInvitees) * 100) : 0;
    @endphp

    <style>
        .fi-header {
            display: none !important;
        }

        .fi-main {
            padding-top: 0 !important;
        }

        .elive-dashboard {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
            background: #F8FAFC;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .elive-content {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 24px 28px 36px;
        }

        .elive-hero {
            border-radius: 22px;
            padding: 24px 30px;
            color: #FFFFFF;
            background: #213B73;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
        }

        .elive-hero-grid {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr) minmax(250px, 340px);
            gap: 22px;
            align-items: center;
        }

        .elive-hero-icon {
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            border-radius: 22px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
        }

        .elive-hero-icon svg {
            width: 34px;
            height: 34px;
        }

        .elive-hero h1 {
            margin: 0;
            max-width: 720px;
            font-size: 27px;
            line-height: 1.15;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .elive-hero p {
            margin: 10px 0 0;
            max-width: 680px;
            color: #E5EDFF;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 600;
        }

        .elive-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .elive-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 15px;
            border-radius: 11px;
            font-size: 13px;
            font-weight: 850;
            text-decoration: none;
            transition: .2s ease;
        }

        .elive-btn:hover {
            transform: translateY(-1px);
        }

        .elive-btn-primary {
            color: #FFFFFF;
            background: #FD9618;
        }

        .elive-btn-light {
            color: #213B73;
            background: #FFFFFF;
        }

        .elive-hero-stats {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            align-items: center;
            text-align: center;
        }

        .elive-hero-stat {
            padding: 0 12px;
            border-left: 1px solid rgba(255, 255, 255, .18);
        }

        .elive-hero-stat:first-child {
            border-left: 0;
        }

        .elive-hero-number {
            font-size: 25px;
            font-weight: 900;
        }

        .elive-hero-label {
            margin-top: 4px;
            color: #DDE8FF;
            font-size: 12px;
            font-weight: 700;
        }

        .elive-card {
            min-width: 0;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        }

        .elive-kpis {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-top: 18px;
            align-items: stretch;
        }

        .elive-kpi {
            min-height: 112px;
            display: flex;
            align-items: center;
            padding: 18px;
        }

        .elive-kpi-inner {
            width: 100%;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .elive-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            flex-shrink: 0;
        }

        .elive-icon svg {
            width: 24px;
            height: 24px;
        }

        .blue {
            color: #2563EB;
            background: #EFF6FF;
        }

        .orange {
            color: #FD9618;
            background: #FFF3E4;
        }

        .green {
            color: #16A34A;
            background: #EAFBF0;
        }

        .purple {
            color: #7C3AED;
            background: #F3EAFE;
        }

        .red {
            color: #DC2626;
            background: #FEF2F2;
        }

        .elive-kpi-label {
            font-size: 13px;
            font-weight: 800;
            color: #64748B;
        }

        .elive-kpi-value {
            margin-top: 5px;
            font-size: 25px;
            line-height: 1;
            font-weight: 900;
            color: #111827;
            white-space: nowrap;
        }

        .elive-kpi-note {
            margin-top: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #16A34A;
            white-space: nowrap;
        }

        .elive-main-grid {
            display: grid;
            grid-template-columns: minmax(350px, 1.12fr) minmax(440px, 1.38fr) minmax(280px, .9fr);
            gap: 18px;
            margin-top: 18px;
            align-items: start;
        }

        .elive-main-grid > .elive-card {
            min-height: 0;
            height: auto;
            align-self: start;
        }

        .elive-bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
            gap: 18px;
            margin-top: 18px;
            align-items: stretch;
        }

        .elive-bottom-grid > .elive-card {
            height: 100%;
        }

        .elive-section {
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 16px;
            height: auto;
        }

        .elive-section-header {
            min-height: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }

        .elive-section-title {
            font-size: 16px;
            font-weight: 900;
            color: #111827;
        }

        .elive-section-subtitle {
            margin-top: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #64748B;
        }

        .elive-link {
            padding: 7px 11px;
            border-radius: 10px;
            background: #EEF4FF;
            color: #213B73;
            font-size: 12px;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
        }

        .elive-rsvp-layout {
            flex: 0;
            display: grid;
            grid-template-columns: 130px minmax(0, 1fr);
            gap: 18px;
            align-items: center;
            margin-top: 12px;
        }

        .elive-donut {
            width: 128px;
            height: 128px;
            display: grid;
            place-items: center;
            position: relative;
            justify-self: center;
            border-radius: 999px;
            background:
                conic-gradient(
                    #22C55E 0 {{ $attendingPercent }}%,
                    #EF4444 {{ $attendingPercent }}% {{ $attendingPercent + $notAttendingPercent }}%,
                    #FD9618 {{ $attendingPercent + $notAttendingPercent }}% 100%
                );
        }

        .elive-donut::after {
            content: "";
            position: absolute;
            width: 88px;
            height: 88px;
            border-radius: 999px;
            background: #FFFFFF;
        }

        .elive-donut-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .elive-donut-value {
            font-size: 20px;
            font-weight: 900;
            color: #111827;
        }

        .elive-donut-label {
            font-size: 10px;
            font-weight: 750;
            color: #64748B;
        }

        .elive-rsvp-list {
            width: 100%;
            min-width: 0;
            display: grid;
            gap: 12px;
        }

        .elive-rsvp-row {
            min-width: 0;
        }

        .elive-rsvp-row-top {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 7px;
        }

        .elive-rsvp-label {
            max-width: none;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            font-size: 13px;
            font-weight: 850;
            color: #334155;
        }

        .elive-rsvp-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .elive-rsvp-name {
            overflow: visible;
            text-overflow: unset;
            white-space: nowrap;
        }

        .elive-rsvp-value {
            flex-shrink: 0;
            text-align: right;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 900;
            color: #334155;
        }

        .elive-progress {
            height: 9px;
            overflow: hidden;
            border-radius: 999px;
            background: #E5E7EB;
        }

        .elive-progress-fill {
            height: 100%;
            border-radius: 999px;
        }

        .elive-message-grid {
            flex: 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            align-content: start;
        }

        .elive-mini-card {
            min-height: 76px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 13px;
            border-radius: 15px;
            background: #F8FAFC;
            border: 1px solid #E5E7EB;
        }

        .elive-mini-label {
            min-height: 28px;
            display: flex;
            align-items: center;
            font-size: 12px;
            font-weight: 850;
            color: #64748B;
        }

        .elive-mini-value {
            margin-top: 6px;
            line-height: 1;
            font-size: 22px;
            font-weight: 900;
            color: #111827;
        }

        .elive-actions-list {
            flex: 0;
            display: grid;
            gap: 10px;
        }

        .elive-action-item {
            width: 100%;
            box-sizing: border-box;
            min-height: 62px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
            text-decoration: none;
        }

        .elive-action-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .elive-action-left > span:last-child {
            min-width: 0;
        }

        .elive-action-title {
            display: block;
            font-size: 13px;
            font-weight: 900;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-action-subtitle {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-small-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            flex-shrink: 0;
        }

        .elive-small-icon svg {
            width: 21px;
            height: 21px;
        }

        .elive-event-list {
            display: grid;
            gap: 10px;
        }

        .elive-event-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 14px;
            align-items: center;
            padding: 12px;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            background: #FFFFFF;
        }

        .elive-event-name {
            font-size: 13px;
            font-weight: 900;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-event-venue {
            margin-top: 3px;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-event-date {
            font-size: 12px;
            font-weight: 900;
            color: #213B73;
            text-align: right;
            white-space: nowrap;
        }

        .elive-event-time {
            margin-top: 3px;
            font-size: 12px;
            color: #64748B;
            text-align: right;
            white-space: nowrap;
        }

        .elive-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            background: #EEF4FF;
            color: #2563EB;
            font-size: 11px;
            font-weight: 900;
        }

        .elive-table-wrap {
            overflow-x: hidden;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
        }

        .elive-table {
            width: 100%;
            min-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            background: #FFFFFF;
        }

        .elive-table th {
            padding: 12px 14px;
            background: #F8FAFC;
            color: #64748B;
            font-size: 11px;
            font-weight: 900;
            text-align: left;
            vertical-align: middle;
        }

        .elive-table td {
            padding: 12px 14px;
            border-top: 1px solid #EEF2F7;
            font-size: 12px;
            font-weight: 650;
            color: #334155;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-pill {
            display: inline-flex;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
        }

        .pill-green {
            color: #15803D;
            background: #DCFCE7;
        }

        .pill-red {
            color: #B91C1C;
            background: #FEE2E2;
        }

        .pill-orange {
            color: #C2410C;
            background: #FFEDD5;
        }

        .elive-empty {
            padding: 26px;
            border-radius: 14px;
            border: 1px dashed #CBD5E1;
            background: #F8FAFC;
            text-align: center;
            color: #64748B;
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 1280px) {
            .elive-hero-grid {
                grid-template-columns: 1fr;
            }

            .elive-hero-stats {
                margin-top: 10px;
            }

            .elive-kpis {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .elive-main-grid {
                grid-template-columns: 1fr;
            }

            .elive-bottom-grid {
                grid-template-columns: 1fr;
            }

            .elive-rsvp-layout {
                grid-template-columns: 135px minmax(0, 1fr);
            }
        }

        @media (max-width: 900px) {
            .elive-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .elive-content {
                padding: 16px;
            }

            .elive-hero {
                padding: 22px;
            }

            .elive-hero h1 {
                font-size: 26px;
            }

            .elive-hero-stats,
            .elive-kpis,
            .elive-rsvp-layout,
            .elive-message-grid,
            .elive-event-item {
                grid-template-columns: 1fr;
            }

            .elive-donut {
                margin: 0 auto;
            }

            .elive-section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .elive-link {
                width: fit-content;
            }
        }
    </style>

    <div class="elive-dashboard">
        <div class="elive-content">
            <section class="elive-hero">
                <div class="elive-hero-grid">
                    <div class="elive-hero-icon">
                        <x-heroicon-o-calendar-days />
                    </div>

                    <div>
                        <h1>Professional Event Command Center</h1>
                        <p>
                            Manage events, invitations, RSVPs, SMS communications and guest check-ins
                            from one powerful dashboard.
                        </p>

                        <div class="elive-hero-actions">
                            <a href="{{ url('/admin/events/create') }}" class="elive-btn elive-btn-primary">
                                <x-heroicon-o-plus style="width: 18px; height: 18px;" />
                                Create Event
                            </a>

                            <a href="{{ url('/admin/gate-check-in') }}" class="elive-btn elive-btn-light">
                                <x-heroicon-o-qr-code style="width: 18px; height: 18px;" />
                                Open Gate Scanner
                            </a>
                        </div>
                    </div>

                    <div class="elive-hero-stats">
                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ $eventsThisMonth ?? $totalEvents }}</div>
                            <div class="elive-hero-label">Events This Month</div>
                        </div>

                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ number_format($totalInvitees) }}</div>
                            <div class="elive-hero-label">Invitees</div>
                        </div>

                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ $responseRate }}%</div>
                            <div class="elive-hero-label">Response Rate</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="elive-kpis">
                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon blue">
                            <x-heroicon-o-calendar-days />
                        </div>
                        <div>
                            <div class="elive-kpi-label">Total Events</div>
                            <div class="elive-kpi-value">{{ number_format($totalEvents) }}</div>
                            <div class="elive-kpi-note">{{ number_format($eventsThisMonth ?? 0) }} this month</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon orange">
                            <x-heroicon-o-users />
                        </div>
                        <div>
                            <div class="elive-kpi-label">Total Invitees</div>
                            <div class="elive-kpi-value">{{ number_format($totalInvitees) }}</div>
                            <div class="elive-kpi-note">{{ number_format($attending) }} attending</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon green">
                            <x-heroicon-o-chat-bubble-left-right />
                        </div>
                        <div>
                            <div class="elive-kpi-label">SMS Balance</div>
                            <div class="elive-kpi-value">{{ $smsBalance }}</div>
                            <div class="elive-kpi-note">SMS credits</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon purple">
                            <x-heroicon-o-check-circle />
                        </div>
                        <div>
                            <div class="elive-kpi-label">Checked In</div>
                            <div class="elive-kpi-value">{{ number_format($checkedInInvitees) }}</div>
                            <div class="elive-kpi-note">{{ $checkedInPercent }}% of invitees</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon blue">
                            <x-heroicon-o-chart-pie />
                        </div>
                        <div>
                            <div class="elive-kpi-label">Response Rate</div>
                            <div class="elive-kpi-value">{{ $responseRate }}%</div>
                            <div class="elive-kpi-note">{{ number_format($attending + $notAttending) }} responded</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="elive-main-grid">
                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">RSVP Overview</div>
                            <div class="elive-section-subtitle">Invitee attendance responses</div>
                        </div>
                        <a href="{{ url('/admin/rsvp-report') }}" class="elive-link">View RSVP Reports</a>
                    </div>

                    <div class="elive-rsvp-layout">
                        <div class="elive-donut">
                            <div class="elive-donut-content">
                                <div class="elive-donut-value">{{ number_format($totalInvitees) }}</div>
                                <div class="elive-donut-label">Total Invitees</div>
                            </div>
                        </div>

                        <div class="elive-rsvp-list">
                            <div class="elive-rsvp-row">
                                <div class="elive-rsvp-row-top">
                                    <span class="elive-rsvp-label">
                                        <span class="elive-rsvp-dot" style="background: #22C55E;"></span>
                                        <span class="elive-rsvp-name">Attending</span>
                                    </span>
                                    <span class="elive-rsvp-value">{{ number_format($attending) }} ({{ $attendingPercent }}%)</span>
                                </div>
                                <div class="elive-progress">
                                    <div class="elive-progress-fill" style="width: {{ $attendingPercent }}%; background: #22C55E;"></div>
                                </div>
                            </div>

                            <div class="elive-rsvp-row">
                                <div class="elive-rsvp-row-top">
                                    <span class="elive-rsvp-label">
                                        <span class="elive-rsvp-dot" style="background: #EF4444;"></span>
                                        <span class="elive-rsvp-name">Not Attending</span>
                                    </span>
                                    <span class="elive-rsvp-value">{{ number_format($notAttending) }} ({{ $notAttendingPercent }}%)</span>
                                </div>
                                <div class="elive-progress">
                                    <div class="elive-progress-fill" style="width: {{ $notAttendingPercent }}%; background: #EF4444;"></div>
                                </div>
                            </div>

                            <div class="elive-rsvp-row">
                                <div class="elive-rsvp-row-top">
                                    <span class="elive-rsvp-label">
                                        <span class="elive-rsvp-dot" style="background: #FD9618;"></span>
                                        <span class="elive-rsvp-name">Pending</span>
                                    </span>
                                    <span class="elive-rsvp-value">{{ number_format($rsvpPending) }} ({{ $pendingPercent }}%)</span>
                                </div>
                                <div class="elive-progress">
                                    <div class="elive-progress-fill" style="width: {{ $pendingPercent }}%; background: #FD9618;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Message Overview</div>
                            <div class="elive-section-subtitle">SMS invitation, reminder, and delivery summary</div>
                        </div>
                        <a href="{{ url('/admin/sms-logs') }}" class="elive-link">View SMS Reports</a>
                    </div>

                    <div class="elive-message-grid">
                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Total SMS</div>
                            <div class="elive-mini-value">{{ number_format($smsTotal) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Sent</div>
                            <div class="elive-mini-value" style="color: #16A34A;">{{ number_format($smsSent) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Failed</div>
                            <div class="elive-mini-value" style="color: #DC2626;">{{ number_format($smsFailed) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Pending</div>
                            <div class="elive-mini-value" style="color: #FD9618;">{{ number_format($smsPending) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Invitation SMS</div>
                            <div class="elive-mini-value">{{ number_format($invitationSms) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">RSVP Reminders</div>
                            <div class="elive-mini-value">{{ number_format($rsvpReminders) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">One Day Before</div>
                            <div class="elive-mini-value">{{ number_format($oneDayBeforeSms) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Event Day SMS</div>
                            <div class="elive-mini-value">{{ number_format($eventDaySms) }}</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Quick Actions</div>
                            <div class="elive-section-subtitle">Common operations</div>
                        </div>
                    </div>

                    <div class="elive-actions-list">
                        <a href="{{ url('/admin/events/create') }}" class="elive-action-item">
                            <span class="elive-action-left">
                                <span class="elive-small-icon blue">
                                    <x-heroicon-o-calendar-days />
                                </span>
                                <span>
                                    <span class="elive-action-title">Create New Event</span>
                                    <span class="elive-action-subtitle">Set up a new event</span>
                                </span>
                            </span>
                            <span style="color:#213B73;font-weight:900;">›</span>
                        </a>

                        <a href="{{ url('/admin/events') }}" class="elive-action-item">
                            <span class="elive-action-left">
                                <span class="elive-small-icon orange">
                                    <x-heroicon-o-users />
                                </span>
                                <span>
                                    <span class="elive-action-title">Import Invitees</span>
                                    <span class="elive-action-subtitle">Upload or import invitee list</span>
                                </span>
                            </span>
                            <span style="color:#213B73;font-weight:900;">›</span>
                        </a>

                        <a href="{{ url('/admin/sms-logs') }}" class="elive-action-item">
                            <span class="elive-action-left">
                                <span class="elive-small-icon blue">
                                    <x-heroicon-o-paper-airplane />
                                </span>
                                <span>
                                    <span class="elive-action-title">Send Message</span>
                                    <span class="elive-action-subtitle">Send SMS to invitees</span>
                                </span>
                            </span>
                            <span style="color:#213B73;font-weight:900;">›</span>
                        </a>

                        <a href="{{ url('/admin/gate-check-in') }}" class="elive-action-item">
                            <span class="elive-action-left">
                                <span class="elive-small-icon green">
                                    <x-heroicon-o-qr-code />
                                </span>
                                <span>
                                    <span class="elive-action-title">Open Gate Scanner</span>
                                    <span class="elive-action-subtitle">Scan QR / invitee ID</span>
                                </span>
                            </span>
                            <span style="color:#213B73;font-weight:900;">›</span>
                        </a>

                        <a href="{{ url('/admin/rsvp-report') }}" class="elive-action-item">
                            <span class="elive-action-left">
                                <span class="elive-small-icon purple">
                                    <x-heroicon-o-chart-bar-square />
                                </span>
                                <span>
                                    <span class="elive-action-title">View RSVP Reports</span>
                                    <span class="elive-action-subtitle">Detailed RSVP analytics</span>
                                </span>
                            </span>
                            <span style="color:#213B73;font-weight:900;">›</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="elive-bottom-grid">
                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Upcoming Events</div>
                            <div class="elive-section-subtitle">Next social events to manage</div>
                        </div>
                        <a href="{{ url('/admin/events') }}" class="elive-link">View All Events</a>
                    </div>

                    <div class="elive-event-list">
                        @forelse ($upcomingEvents as $event)
                            <div class="elive-event-item">
                                <div>
                                    <div class="elive-event-name">
                                        {{ $event->title ?? $event->name ?? 'Untitled Event' }}
                                    </div>

                                    <div class="elive-event-venue">
                                        {{ $event->venue_name ?? $event->venue ?? 'Venue not set' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="elive-event-date">
                                        @if (isset($event->event_date))
                                            {{ \Carbon\Carbon::parse($event->event_date)->format('M d, Y') }}
                                        @elseif (isset($event->date))
                                            {{ \Carbon\Carbon::parse($event->date)->format('M d, Y') }}
                                        @else
                                            Date not set
                                        @endif
                                    </div>

                                    <div class="elive-event-time">
                                        {{ $event->start_time ?? '' }}
                                    </div>
                                </div>

                                <span class="elive-badge">
                                    Upcoming
                                </span>
                            </div>
                        @empty
                            <div class="elive-empty">
                                No upcoming events found.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Recent SMS Activity</div>
                            <div class="elive-section-subtitle">Latest invitation and reminder messages</div>
                        </div>
                        <a href="{{ url('/admin/sms-logs') }}" class="elive-link">View All Logs</a>
                    </div>

                    <div class="elive-table-wrap">
                        <table class="elive-table">
                            <thead>
                                <tr>
                                    <th>Message / Phone</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($recentSmsLogs as $log)
                                    @php
                                        $status = $log->status ?? 'pending';

                                        $pillClass = match ($status) {
                                            'sent', 'delivered', 'submitted', 'success' => 'pill-green',
                                            'failed', 'error' => 'pill-red',
                                            default => 'pill-orange',
                                        };

                                        $type = $log->sms_type
                                            ?? $log->message_type
                                            ?? $log->type
                                            ?? $log->category
                                            ?? 'SMS';
                                    @endphp

                                    <tr>
                                        <td>{{ $log->phone ?? '-' }}</td>
                                        <td>{{ str($type)->replace('_', ' ')->title() }}</td>
                                        <td>
                                            <span class="elive-pill {{ $pillClass }}">
                                                {{ str($status)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                        <td>{{ optional($log->created_at)->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 30px;">
                                            No SMS activity yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>