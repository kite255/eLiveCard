<x-filament-panels::page>
    @php
        $attendingPercent = $totalInvitees > 0 ? round(($attending / $totalInvitees) * 100) : 0;
        $notAttendingPercent = $totalInvitees > 0 ? round(($notAttending / $totalInvitees) * 100) : 0;
        $pendingPercent = $totalInvitees > 0 ? round(($rsvpPending / $totalInvitees) * 100) : 0;
        $checkedInPercent = $totalInvitees > 0 ? round(($checkedInInvitees / $totalInvitees) * 100) : 0;

        /*
         * The scanner must always be opened for an explicitly selected event.
         * This prevents ambiguity when two or more events are active.
         */
        $scannerEvent = $selectedEvent;
        $scannerUrl = $scannerEvent
            ? route('gate.check-in.show', ['event' => $scannerEvent->getKey()])
            : null;

        $checkInDashboardUrl = $selectedEvent
            ? \App\Filament\Resources\EventResource::getUrl(
                'check-in-dashboard',
                ['record' => $selectedEvent]
            )
            : null;

        $eventWorkspaceUrl = $selectedEvent
            ? \App\Filament\Resources\EventResource::getUrl(
                'view',
                ['record' => $selectedEvent]
            )
            : null;

        $remainingGateGuests = max(
            (int) $totalAllowedGuests - (int) $checkedInGuests,
            0
        );
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 14px 20px 20px;
        }

        .elive-hero {
            border-radius: 18px;
            padding: 16px 22px;
            color: #FFFFFF;
            background: #213B73;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
        }

        .elive-hero-grid {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr) minmax(250px, 340px);
            gap: 10px;
            align-items: center;
        }

        .elive-hero-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 22px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
        }

        .elive-hero-icon svg {
            width: 28px;
            height: 28px;
        }

        .elive-hero h1 {
            margin: 0;
            max-width: 720px;
            font-size: 22px;
            line-height: 1.15;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .elive-hero p {
            margin: 5px 0 0;
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
            margin-top: 12px;
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

        .elive-btn-disabled {
            color: rgba(255, 255, 255, .75);
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .2);
            cursor: not-allowed;
            opacity: .8;
        }

        .elive-btn-disabled:hover {
            transform: none;
        }

        .elive-action-disabled {
            cursor: not-allowed;
            opacity: .55;
            pointer-events: none;
        }

        .elive-scanner-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 14px;
            padding: 11px 13px;
            border-radius: 12px;
            color: #92400E;
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.5;
        }

        .elive-scanner-notice svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin-top: 1px;
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
            font-size: 20px;
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
            gap: 10px;
            margin-top: 12px;
            align-items: stretch;
        }

        .elive-kpi {
            min-height: 84px;
            display: flex;
            align-items: center;
            padding: 10px 12px;
        }

        .elive-kpi-inner {
            width: 100%;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .elive-icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            flex-shrink: 0;
        }

        .elive-icon svg {
            width: 20px;
            height: 20px;
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
            font-size: 20px;
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

        .elive-dashboard-grid {
            display: grid;
            grid-template-columns:
                minmax(300px, 1fr)
                minmax(440px, 1.35fr)
                minmax(270px, .82fr);
            grid-template-areas:
                "rsvp messages actions"
                "attendance checkins actions"
                "attendance checkins alerts";
            gap: 18px;
            margin-top: 12px;
            align-items: stretch;
        }

        .elive-main-grid,
        .elive-operations-grid {
            display: contents;
        }

        .elive-main-grid > :nth-child(1) {
            grid-area: rsvp;
        }

        .elive-main-grid > :nth-child(2) {
            grid-area: messages;
        }

        .elive-main-grid > :nth-child(3) {
            grid-area: actions;
        }

        .elive-operations-grid > :nth-child(1) {
            grid-area: attendance;
        }

        .elive-operations-grid > :nth-child(2) {
            grid-area: checkins;
        }

        .elive-operations-grid > :nth-child(3) {
            grid-area: alerts;
        }

        .elive-dashboard-grid > .elive-card {
            min-height: 0;
            height: 100%;
        }

        .elive-bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
            gap: 18px;
            margin-top: 12px;
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
            gap: 8px;
        }

        .elive-action-item {
            width: 100%;
            box-sizing: border-box;
            min-height: 56px;
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


        .elive-filter-card {
            margin-top: 12px;
            padding: 18px 20px;
        }

        .elive-filter-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .elive-filter-copy {
            min-width: 0;
        }

        .elive-filter-title {
            font-size: 15px;
            font-weight: 900;
            color: #111827;
        }

        .elive-filter-subtitle {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 650;
            color: #64748B;
        }

        .elive-filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            width: min(100%, 430px);
        }

        .elive-select {
            width: 100%;
            min-width: 0;
            height: 44px;
            padding: 0 46px 0 14px;
            border-radius: 12px;
            border: 1px solid #CBD5E1;
            color: #111827;
            font-size: 13px;
            font-weight: 750;
            outline: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #FFFFFF;
            background-image:
                linear-gradient(45deg, transparent 50%, #64748B 50%),
                linear-gradient(135deg, #64748B 50%, transparent 50%);
            background-position:
                calc(100% - 20px) 18px,
                calc(100% - 14px) 18px;
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
        }

        .elive-select:focus {
            border-color: #213B73;
            box-shadow: 0 0 0 3px rgba(33, 59, 115, .12);
        }

        .elive-clear-btn {
            height: 44px;
            padding: 0 14px;
            border: 1px solid #CBD5E1;
            border-radius: 12px;
            background: #FFFFFF;
            color: #475569;
            font-size: 12px;
            font-weight: 850;
            cursor: pointer;
            white-space: nowrap;
        }

        .elive-selected-event {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #E5E7EB;
        }

        .elive-selected-label {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94A3B8;
        }

        .elive-selected-value {
            margin-top: 5px;
            font-size: 13px;
            font-weight: 850;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .elive-rsvp-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .elive-rsvp-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 14px;
        }

        .elive-channel-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .elive-channel {
            padding: 14px;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            background: #F8FAFC;
        }

        .elive-channel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .elive-channel-name {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #111827;
            font-size: 13px;
            font-weight: 900;
        }

        .elive-channel-total {
            color: #213B73;
            font-size: 18px;
            font-weight: 900;
        }

        .elive-channel-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .elive-channel-stat {
            padding: 10px;
            border-radius: 12px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
        }

        .elive-channel-stat-label {
            color: #64748B;
            font-size: 10px;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .elive-channel-stat-value {
            margin-top: 4px;
            color: #111827;
            font-size: 18px;
            font-weight: 900;
        }

        .elive-attendance-list {
            display: grid;
            gap: 12px;
        }

        .elive-attendance-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 11px;
            border-bottom: 1px solid #EEF2F7;
        }

        .elive-attendance-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .elive-attendance-label {
            color: #64748B;
            font-size: 12px;
            font-weight: 750;
        }

        .elive-attendance-value {
            color: #111827;
            font-size: 14px;
            font-weight: 900;
        }

        .elive-checkin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .elive-checkin-table th {
            padding: 10px 9px;
            color: #64748B;
            background: #F8FAFC;
            font-size: 10px;
            font-weight: 900;
            text-align: left;
        }

        .elive-checkin-table td {
            padding: 10px 9px;
            border-top: 1px solid #EEF2F7;
            color: #334155;
            font-size: 11px;
            font-weight: 650;
        }

        .elive-alert-list {
            display: grid;
            gap: 9px;
        }

        .elive-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px;
            border: 1px solid #E5E7EB;
            border-radius: 13px;
            background: #FFFFFF;
            text-decoration: none;
        }

        .elive-alert-dot {
            width: 10px;
            height: 10px;
            margin-top: 4px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .alert-danger .elive-alert-dot {
            background: #DC2626;
        }

        .alert-warning .elive-alert-dot {
            background: #FD9618;
        }

        .alert-info .elive-alert-dot {
            background: #2563EB;
        }

        .alert-success .elive-alert-dot {
            background: #16A34A;
        }

        .elive-alert-title {
            color: #111827;
            font-size: 12px;
            font-weight: 900;
        }

        .elive-alert-description {
            margin-top: 3px;
            color: #64748B;
            font-size: 10px;
            font-weight: 650;
            line-height: 1.4;
        }


        .elive-events-snapshot {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .elive-event-snapshot {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 13px 14px;
        }

        .elive-event-snapshot-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 13px;
        }

        .elive-event-snapshot-icon svg {
            width: 22px;
            height: 22px;
        }

        .elive-event-snapshot-title {
            color: #111827;
            font-size: 13px;
            font-weight: 900;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-event-snapshot-meta {
            margin-top: 4px;
            color: #64748B;
            font-size: 11px;
            font-weight: 650;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-event-snapshot-side {
            min-width: 110px;
            text-align: right;
        }

        .elive-event-snapshot-label {
            display: inline-flex;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
        }

        .elive-event-snapshot-date {
            margin-top: 6px;
            color: #213B73;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }

        .snapshot-active { color: #15803D; background: #DCFCE7; }
        .snapshot-upcoming { color: #C2410C; background: #FFEDD5; }


        .elive-officer-events {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .elive-officer-event {
            min-width: 0;
            padding: 15px;
        }

        .elive-officer-event-title {
            color: #213B73;
            font-size: 15px;
            font-weight: 900;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .elive-officer-event-meta {
            margin-top: 7px;
            color: #64748B;
            font-size: 12px;
            font-weight: 650;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .elive-officer-event-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 13px;
        }

        .elive-officer-link {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border-radius: 11px;
            background: #213B73;
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 900;
            text-align: center;
            text-decoration: none;
        }

        .elive-officer-link.orange-link {
            background: #FD9618;
            color: #111827;
        }

        .elive-mobile-checkins {
            display: none;
        }

        .elive-mobile-checkin {
            padding: 13px;
            border: 1px solid #E5E7EB;
            border-radius: 13px;
            background: #FFFFFF;
        }

        .elive-mobile-checkin-name {
            color: #111827;
            font-size: 13px;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .elive-mobile-checkin-meta {
            margin-top: 6px;
            color: #64748B;
            font-size: 11px;
            font-weight: 650;
            line-height: 1.5;
        }

        .elive-dashboard,
        .elive-dashboard *,
        .elive-dashboard *::before,
        .elive-dashboard *::after {
            box-sizing: border-box;
        }

        .elive-dashboard img,
        .elive-dashboard svg,
        .elive-dashboard video,
        .elive-dashboard canvas {
            max-width: 100%;
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
            .elive-dashboard-grid {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                grid-template-areas:
                    "rsvp messages"
                    "attendance checkins"
                    "actions alerts";
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
            .elive-dashboard-grid {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "rsvp"
                    "messages"
                    "actions"
                    "attendance"
                    "checkins"
                    "alerts";
            }

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
            .elive-events-snapshot,
            .elive-rsvp-layout,
            .elive-message-grid,
            .elive-event-item,
            .elive-channel-grid,
            .elive-rsvp-summary {
                grid-template-columns: 1fr;
            }

            .elive-donut {
                margin: 0 auto;
            }


            .elive-filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .elive-filter-controls {
                width: 100%;
            }

            .elive-selected-event {
                grid-template-columns: 1fr 1fr;
            }

            .elive-officer-events {
                grid-template-columns: 1fr;
            }

            .elive-officer-event-actions {
                grid-template-columns: 1fr;
            }

            .elive-table-wrap {
                display: none;
            }

            .elive-mobile-checkins {
                display: grid;
                gap: 9px;
            }

            .elive-section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .elive-link {
                width: fit-content;
            }
        }

        @media (max-width: 520px) {
            .elive-content {
                padding: 10px;
            }

            .elive-hero {
                padding: 16px;
                border-radius: 16px;
            }

            .elive-hero h1 {
                font-size: 22px;
            }

            .elive-kpis,
            .elive-selected-event,
            .elive-rsvp-summary,
            .elive-channel-stats {
                grid-template-columns: 1fr;
            }

            .elive-filter-card,
            .elive-section {
                padding: 14px;
            }

            .elive-filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .elive-clear-btn,
            .elive-select {
                width: 100%;
                min-height: 48px;
            }

            .elive-btn {
                width: 100%;
                justify-content: center;
            }

            .elive-hero-actions {
                display: grid;
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="elive-dashboard">
        <div class="elive-content">
            @if ($isCheckInOfficer)

            <section class="elive-hero">
                <div class="elive-hero-grid">
                    <div class="elive-hero-icon">
                        <x-heroicon-o-qr-code />
                    </div>

                    <div>
                        <h1>Welcome, {{ $userName }} — Gate Operations</h1>
                        <p>
                            Scan invitation cards, search guests manually, confirm guest limits,
                            and monitor your assigned event check-ins.
                        </p>

                        <div class="elive-hero-actions">
                            @if ($scannerUrl)
                                <a href="{{ $scannerUrl }}" class="elive-btn elive-btn-primary">
                                    <x-heroicon-o-qr-code style="width:18px;height:18px;" />
                                    Open QR Scanner
                                </a>
                            @endif

                            @if ($checkInDashboardUrl)
                                <a href="{{ $checkInDashboardUrl }}" class="elive-btn elive-btn-light">
                                    <x-heroicon-o-magnifying-glass style="width:18px;height:18px;" />
                                    Manual Guest Search
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="elive-hero-stats">
                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ number_format($totalAllowedGuests) }}</div>
                            <div class="elive-hero-label">Expected</div>
                        </div>

                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ number_format($checkedInGuests) }}</div>
                            <div class="elive-hero-label">Checked In</div>
                        </div>

                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ number_format($remainingGateGuests) }}</div>
                            <div class="elive-hero-label">Remaining</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="elive-card elive-filter-card">
                <div class="elive-filter-row">
                    <div class="elive-filter-copy">
                        <div class="elive-filter-title">Assigned Event</div>
                        <div class="elive-filter-subtitle">
                            Select only an event assigned to you for gate check-in.
                        </div>
                    </div>

                    <div class="elive-filter-controls">
                        <select
                            wire:model.live="selectedEventId"
                            class="elive-select"
                            aria-label="Select assigned event"
                        >
                            <option value="">Select assigned event</option>

                            @foreach ($eventOptions as $eventId => $eventName)
                                <option value="{{ $eventId }}">{{ $eventName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if (! $selectedEvent)
                    <div class="elive-scanner-notice">
                        <x-heroicon-o-information-circle />
                        <span>Select an assigned event to activate the scanner and manual search tools.</span>
                    </div>
                @endif

                @if ($selectedEvent)
                    <div class="elive-selected-event">
                        <div>
                            <div class="elive-selected-label">Event</div>
                            <div class="elive-selected-value">
                                {{ $selectedEvent->title ?? $selectedEvent->name ?? 'Event' }}
                            </div>
                        </div>

                        <div>
                            <div class="elive-selected-label">Date</div>
                            <div class="elive-selected-value">
                                {{ $selectedEvent->event_date
                                    ? \Illuminate\Support\Carbon::parse($selectedEvent->event_date)->format('d M Y')
                                    : 'Not set'
                                }}
                            </div>
                        </div>

                        <div>
                            <div class="elive-selected-label">Venue</div>
                            <div class="elive-selected-value">
                                {{ $selectedEvent->venue_name ?? $selectedEvent->venue_address ?? 'Not set' }}
                            </div>
                        </div>

                        <div>
                            <div class="elive-selected-label">Status</div>
                            <div class="elive-selected-value">
                                {{ str($selectedEvent->status ?? 'draft')->replace('_', ' ')->title() }}
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <section class="elive-kpis">
                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon blue"><x-heroicon-o-users /></div>
                        <div>
                            <div class="elive-kpi-label">Expected Guests</div>
                            <div class="elive-kpi-value">{{ number_format($totalAllowedGuests) }}</div>
                            <div class="elive-kpi-note">Selected event allowance</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon green"><x-heroicon-o-check-circle /></div>
                        <div>
                            <div class="elive-kpi-label">Guests Checked In</div>
                            <div class="elive-kpi-value">{{ number_format($checkedInGuests) }}</div>
                            <div class="elive-kpi-note">{{ $guestCheckInPercent }}% progress</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon orange"><x-heroicon-o-user-plus /></div>
                        <div>
                            <div class="elive-kpi-label">Remaining Guests</div>
                            <div class="elive-kpi-value">{{ number_format($remainingGateGuests) }}</div>
                            <div class="elive-kpi-note">Remaining allowance</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon purple"><x-heroicon-o-clipboard-document-check /></div>
                        <div>
                            <div class="elive-kpi-label">Transactions</div>
                            <div class="elive-kpi-value">{{ number_format($checkInTransactions) }}</div>
                            <div class="elive-kpi-note">Recorded check-ins</div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-kpi">
                    <div class="elive-kpi-inner">
                        <div class="elive-icon blue"><x-heroicon-o-chart-bar-square /></div>
                        <div>
                            <div class="elive-kpi-label">Partial Check-ins</div>
                            <div class="elive-kpi-value">{{ number_format($partiallyCheckedInInvitees) }}</div>
                            <div class="elive-kpi-note">Invitees with balance</div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="elive-bottom-grid">
                <section class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Gate Quick Actions</div>
                            <div class="elive-section-subtitle">Only assigned-event gate tools are available.</div>
                        </div>
                    </div>

                    <div class="elive-actions-list">
                        @if ($scannerUrl)
                            <a href="{{ $scannerUrl }}" class="elive-action-item">
                                <span class="elive-action-left">
                                    <span class="elive-small-icon orange">
                                        <x-heroicon-o-qr-code />
                                    </span>
                                    <span>
                                        <span class="elive-action-title">Open QR Scanner</span>
                                        <span class="elive-action-subtitle">Scan and verify invitation cards</span>
                                    </span>
                                </span>
                                <span style="color:#213B73;font-weight:900;">›</span>
                            </a>
                        @endif

                        @if ($checkInDashboardUrl)
                            <a href="{{ $checkInDashboardUrl }}" class="elive-action-item">
                                <span class="elive-action-left">
                                    <span class="elive-small-icon blue">
                                        <x-heroicon-o-magnifying-glass />
                                    </span>
                                    <span>
                                        <span class="elive-action-title">Manual Guest Search</span>
                                        <span class="elive-action-subtitle">Search by name, phone or serial number</span>
                                    </span>
                                </span>
                                <span style="color:#213B73;font-weight:900;">›</span>
                            </a>
                        @endif

                        <a href="{{ url('/admin/gate-check-in') }}" class="elive-action-item">
                            <span class="elive-action-left">
                                <span class="elive-small-icon green">
                                    <x-heroicon-o-calendar-days />
                                </span>
                                <span>
                                    <span class="elive-action-title">Assigned Events</span>
                                    <span class="elive-action-subtitle">View all active assignments</span>
                                </span>
                            </span>
                            <span style="color:#213B73;font-weight:900;">›</span>
                        </a>
                    </div>
                </section>

                <section class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">My Recent Check-ins</div>
                            <div class="elive-section-subtitle">Latest guests processed by your account</div>
                        </div>
                    </div>

                    <div class="elive-table-wrap">
                        <table class="elive-checkin-table">
                            <thead>
                                <tr>
                                    <th>Invitee</th>
                                    <th>Guests</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($officerRecentCheckIns as $checkIn)
                                    <tr>
                                        <td>{{ $checkIn->invitee?->name ?? 'Unknown invitee' }}</td>
                                        <td>{{ number_format((int) ($checkIn->guests_checked_in ?? 1)) }}</td>
                                        <td>{{ str($checkIn->checkin_method ?? 'manual')->headline() }}</td>
                                        <td>{{ str($checkIn->status ?? 'successful')->headline() }}</td>
                                        <td>
                                            {{ $checkIn->checked_in_at
                                                ? \Illuminate\Support\Carbon::parse($checkIn->checked_in_at)->format('H:i')
                                                : '—'
                                            }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding:22px;text-align:center;color:#64748B;">
                                            No check-ins recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="elive-mobile-checkins">
                        @forelse ($officerRecentCheckIns as $checkIn)
                            <article class="elive-mobile-checkin">
                                <div class="elive-mobile-checkin-name">
                                    {{ $checkIn->invitee?->name ?? 'Unknown invitee' }}
                                </div>
                                <div class="elive-mobile-checkin-meta">
                                    {{ (int) ($checkIn->guests_checked_in ?? 1) }} guest(s)
                                    · {{ str($checkIn->checkin_method ?? 'manual')->headline() }}
                                    · {{ $checkIn->checked_in_at
                                        ? \Illuminate\Support\Carbon::parse($checkIn->checked_in_at)->format('H:i')
                                        : '—'
                                    }}
                                </div>
                            </article>
                        @empty
                            <div class="elive-empty">No check-ins recorded yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="elive-card elive-section" style="margin-top:12px;">
                <div class="elive-section-header">
                    <div>
                        <div class="elive-section-title">Assigned Events</div>
                        <div class="elive-section-subtitle">Events currently available for gate operations</div>
                    </div>
                </div>

                <div class="elive-officer-events">
                    @forelse ($accessibleEvents as $event)
                        <article class="elive-card elive-officer-event">
                            <div class="elive-officer-event-title">
                                {{ $event->title ?? $event->name ?? 'Untitled Event' }}
                            </div>

                            <div class="elive-officer-event-meta">
                                {{ $event->event_date
                                    ? \Illuminate\Support\Carbon::parse($event->event_date)->format('d M Y')
                                    : 'Date not set'
                                }}
                                <br>
                                {{ $event->venue_name ?? $event->venue_address ?? 'Venue not set' }}
                            </div>

                            <div class="elive-officer-event-actions">
                                <a
                                    href="{{ route('gate.check-in.show', $event) }}"
                                    class="elive-officer-link orange-link"
                                >
                                    Scanner
                                </a>

                                <a
                                    href="{{ \App\Filament\Resources\EventResource::getUrl('check-in-dashboard', ['record' => $event]) }}"
                                    class="elive-officer-link"
                                >
                                    Search
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="elive-empty">No assigned events found.</div>
                    @endforelse
                </div>
            </section>

            @else

            <section class="elive-hero">
                <div class="elive-hero-grid">
                    <div class="elive-hero-icon">
                        <x-heroicon-o-calendar-days />
                    </div>

                    <div>
                        <h1>Welcome, {{ $userName }} — Event Command Center</h1>
                        <p>
                            Manage accessible events, invitations, RSVPs, messages, approvals and guest check-ins
                            from one professional dashboard.
                        </p>

                        <div class="elive-hero-actions">
                            @if ($isSuperAdmin || $isEventManager)
                                <a href="{{ url('/admin/events/create') }}" class="elive-btn elive-btn-primary">
                                    <x-heroicon-o-plus style="width: 18px; height: 18px;" />
                                    Create Event
                                </a>
                            @endif

                            @if ($scannerUrl)
                                <a href="{{ $scannerUrl }}" class="elive-btn elive-btn-light">
                                    <x-heroicon-o-qr-code style="width: 18px; height: 18px;" />
                                    Open Gate Scanner
                                </a>
                            @else
                                <button
                                    type="button"
                                    class="elive-btn elive-btn-disabled"
                                    disabled
                                    title="Select an event before opening the scanner"
                                >
                                    <x-heroicon-o-qr-code style="width: 18px; height: 18px;" />
                                    Select Event First
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="elive-hero-stats">
                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ $eventsThisMonth ?? $totalEvents }}</div>
                            <div class="elive-hero-label">Events This Month</div>
                        </div>

                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ number_format($totalInvitees) }}</div>
                            <div class="elive-hero-label">{{ $selectedEventId ? 'Event Invitees' : 'Invitees' }}</div>
                        </div>

                        <div class="elive-hero-stat">
                            <div class="elive-hero-number">{{ $responseRate }}%</div>
                            <div class="elive-hero-label">Response Rate</div>
                        </div>
                    </div>
                </div>
            </section>


            <section class="elive-card elive-filter-card">
                <div class="elive-filter-row">
                    <div class="elive-filter-copy">
                        <div class="elive-filter-title">Event Dashboard Filter</div>
                        <div class="elive-filter-subtitle">
                            Select an event to filter dashboard data and activate the correct event scanner.
                        </div>
                    </div>

                    <div class="elive-filter-controls">
                        <select
                            wire:model.live="selectedEventId"
                            class="elive-select"
                            aria-label="Select event"
                        >
                            <option value="">All Events — scanner disabled</option>

                            @foreach ($eventOptions as $eventId => $eventName)
                                <option value="{{ $eventId }}">
                                    {{ $eventName }}
                                </option>
                            @endforeach
                        </select>

                        @if ($selectedEventId)
                            <button
                                type="button"
                                wire:click="clearEventFilter"
                                class="elive-clear-btn"
                            >
                                Clear
                            </button>
                        @endif
                    </div>
                </div>

                @if (! $selectedEvent)
                    <div class="elive-scanner-notice">
                        <x-heroicon-o-information-circle />
                        <span>
                            Select an event before opening the gate scanner. This prevents invitees
                            from one active event being checked into another event.
                        </span>
                    </div>
                @endif

                @if ($selectedEvent)
                    @php
                        $selectedEventDate = $selectedEvent->event_date
                            ?? $selectedEvent->date
                            ?? null;

                        $selectedEventName = $selectedEvent->title
                            ?? $selectedEvent->name
                            ?? $selectedEvent->event_name
                            ?? 'Event';

                        $selectedEventVenue = $selectedEvent->venue_name
                            ?? $selectedEvent->venue
                            ?? 'Not set';
                    @endphp

                    <div class="elive-selected-event">
                        <div>
                            <div class="elive-selected-label">Event</div>
                            <div class="elive-selected-value">{{ $selectedEventName }}</div>
                        </div>

                        <div>
                            <div class="elive-selected-label">Date</div>
                            <div class="elive-selected-value">
                                {{ $selectedEventDate
                                    ? \Illuminate\Support\Carbon::parse($selectedEventDate)->format('d M Y')
                                    : 'Not set' }}
                            </div>
                        </div>

                        <div>
                            <div class="elive-selected-label">Venue</div>
                            <div class="elive-selected-value">{{ $selectedEventVenue }}</div>
                        </div>

                        <div>
                            <div class="elive-selected-label">Status</div>
                            <div class="elive-selected-value">
                                {{ str($selectedEvent->status ?? 'draft')->replace('_', ' ')->title() }}
                            </div>
                        </div>
                    </div>
                @endif
            </section>


            <section class="elive-events-snapshot">
                <div class="elive-card elive-event-snapshot">
                    <div class="elive-event-snapshot-icon green">
                        <x-heroicon-o-bolt />
                    </div>

                    <div>
                        <div class="elive-event-snapshot-title">
                            {{ $activeEvent?->title ?? $activeEvent?->name ?? $activeEvent?->event_name ?? 'No active event' }}
                        </div>
                        <div class="elive-event-snapshot-meta">
                            @if ($activeEvent)
                                {{ $activeEvent->venue_name ?? $activeEvent->venue ?? 'Venue not set' }}
                                @if ($activeEvent->start_time ?? null)
                                    • {{ $activeEvent->start_time }}
                                @endif
                            @else
                                No active event is currently available.
                            @endif
                        </div>
                    </div>

                    <div class="elive-event-snapshot-side">
                        <span class="elive-event-snapshot-label snapshot-active">Active Event</span>
                        <div class="elive-event-snapshot-date">
                            @if ($activeEvent?->event_date ?? $activeEvent?->date ?? null)
                                {{ \Illuminate\Support\Carbon::parse($activeEvent->event_date ?? $activeEvent->date)->format('d M Y') }}
                            @else
                                Not scheduled
                            @endif
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-event-snapshot">
                    <div class="elive-event-snapshot-icon orange">
                        <x-heroicon-o-calendar-days />
                    </div>

                    <div>
                        <div class="elive-event-snapshot-title">
                            {{ $upcomingEvent?->title ?? $upcomingEvent?->name ?? $upcomingEvent?->event_name ?? 'No upcoming event' }}
                        </div>
                        <div class="elive-event-snapshot-meta">
                            @if ($upcomingEvent)
                                {{ $upcomingEvent->venue_name ?? $upcomingEvent->venue ?? 'Venue not set' }}
                                @if ($upcomingEvent->start_time ?? null)
                                    • {{ $upcomingEvent->start_time }}
                                @endif
                            @else
                                No future event is currently scheduled.
                            @endif
                        </div>
                    </div>

                    <div class="elive-event-snapshot-side">
                        <span class="elive-event-snapshot-label snapshot-upcoming">Upcoming Event</span>
                        <div class="elive-event-snapshot-date">
                            @if ($upcomingEvent?->event_date ?? $upcomingEvent?->date ?? null)
                                {{ \Illuminate\Support\Carbon::parse($upcomingEvent->event_date ?? $upcomingEvent->date)->format('d M Y') }}
                            @else
                                Not scheduled
                            @endif
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
                            <div
                                class="elive-kpi-value"
                                @style([
                                    'font-size:16px' => ! is_numeric(str_replace(',', '', (string) $smsBalance)),
                                    'color:#DC2626' => in_array($smsBalance, ['Unavailable', 'Check provider'], true),
                                    'color:#C2410C' => $smsBalance === 'Not configured',
                                ])
                            >
                                {{ $smsBalance }}
                            </div>
                            <div class="elive-kpi-note">
                                @if ($smsBalance === 'Not configured')
                                    Configure balance API
                                @elseif (in_array($smsBalance, ['Unavailable', 'Check provider'], true))
                                    Provider connection issue
                                @else
                                    Available SMS credits
                                @endif
                            </div>
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

            <div class="elive-dashboard-grid">
                <section class="elive-main-grid">
                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">RSVP Overview</div>
                            <div class="elive-section-subtitle">{{ $selectedEventId ? 'Selected event attendance responses' : 'Invitee attendance responses' }}</div>
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

                    <div class="elive-rsvp-summary">
                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Responded</div>
                            <div class="elive-mini-value">{{ number_format($respondedInvitees) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Confirmed Guests</div>
                            <div class="elive-mini-value">{{ number_format($confirmedGuests) }}</div>
                        </div>

                        <div class="elive-mini-card">
                            <div class="elive-mini-label">RSVP Progress</div>
                            <div class="elive-mini-value" style="color:#213B73;">{{ $rsvpProgress }}%</div>
                        </div>
                    </div>

                    <div class="elive-rsvp-actions">
                        <a href="{{ url('/admin/rsvp-report?status=pending') }}" class="elive-link">
                            View Pending
                        </a>

                        <a href="{{ url('/admin/events') }}" class="elive-link" style="background:#FFF3E4;color:#C2410C;">
                            Send RSVP Reminder
                        </a>

                        <a href="{{ url('/admin/rsvp-report') }}" class="elive-link">
                            Export RSVP Report
                        </a>
                    </div>
                </div>

                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">WhatsApp & SMS Overview</div>
                            <div class="elive-section-subtitle">{{ $selectedEventId ? 'Selected event communication summary' : 'Combined message delivery and response summary' }}</div>
                        </div>
                        <a href="{{ url('/admin/sms-logs') }}" class="elive-link">View Message Reports</a>
                    </div>

                    <div class="elive-channel-grid">
                        <div class="elive-channel">
                            <div class="elive-channel-header">
                                <div class="elive-channel-name">
                                    <x-heroicon-o-chat-bubble-left-right style="width:20px;height:20px;color:#2563EB;" />
                                    SMS
                                </div>
                                <div class="elive-channel-total">{{ number_format($smsTotal) }}</div>
                            </div>

                            <div class="elive-channel-stats">
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Accepted / Sent</div>
                                    <div class="elive-channel-stat-value" style="color:#16A34A;">{{ number_format($smsSent) }}</div>
                                </div>
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Delivered</div>
                                    <div class="elive-channel-stat-value">{{ number_format($smsDelivered) }}</div>
                                </div>
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Pending</div>
                                    <div class="elive-channel-stat-value" style="color:#FD9618;">{{ number_format($smsPending) }}</div>
                                </div>
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Failed</div>
                                    <div class="elive-channel-stat-value" style="color:#DC2626;">{{ number_format($smsFailed) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="elive-channel">
                            <div class="elive-channel-header">
                                <div class="elive-channel-name">
                                    <x-heroicon-o-chat-bubble-oval-left-ellipsis style="width:20px;height:20px;color:#16A34A;" />
                                    WhatsApp
                                </div>
                                <div class="elive-channel-total">{{ number_format($whatsAppTotal) }}</div>
                            </div>

                            <div class="elive-channel-stats">
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Accepted / Sent</div>
                                    <div class="elive-channel-stat-value" style="color:#16A34A;">{{ number_format($whatsAppSent) }}</div>
                                </div>
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Delivered</div>
                                    <div class="elive-channel-stat-value">{{ number_format($whatsAppDelivered) }}</div>
                                </div>
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Read / Replied</div>
                                    <div class="elive-channel-stat-value">{{ number_format($whatsAppRead + $whatsAppReplied) }}</div>
                                </div>
                                <div class="elive-channel-stat">
                                    <div class="elive-channel-stat-label">Failed</div>
                                    <div class="elive-channel-stat-value" style="color:#DC2626;">{{ number_format($whatsAppFailed) }}</div>
                                </div>
                            </div>
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
                        @if ($isSuperAdmin || $isEventManager)
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
                        @endif

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

                        @if ($scannerUrl)
                            <a href="{{ $scannerUrl }}" class="elive-action-item">
                                <span class="elive-action-left">
                                    <span class="elive-small-icon green">
                                        <x-heroicon-o-qr-code />
                                    </span>
                                    <span>
                                        <span class="elive-action-title">Open Gate Scanner</span>
                                        <span class="elive-action-subtitle">
                                            {{ $selectedEvent?->title ?? $selectedEvent?->name ?? 'Selected event' }}
                                        </span>
                                    </span>
                                </span>
                                <span style="color:#213B73;font-weight:900;">›</span>
                            </a>
                        @else
                            <div class="elive-action-item elive-action-disabled">
                                <span class="elive-action-left">
                                    <span class="elive-small-icon green">
                                        <x-heroicon-o-qr-code />
                                    </span>
                                    <span>
                                        <span class="elive-action-title">Select Event First</span>
                                        <span class="elive-action-subtitle">Choose an event from the filter</span>
                                    </span>
                                </span>
                            </div>
                        @endif

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


            <section class="elive-operations-grid">
                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Guest Attendance</div>
                            <div class="elive-section-subtitle">Confirmed and checked-in guest progress</div>
                        </div>
                    </div>

                    <div class="elive-attendance-list">
                        <div class="elive-attendance-row">
                            <span class="elive-attendance-label">Allowed Guests</span>
                            <span class="elive-attendance-value">{{ number_format($totalAllowedGuests) }}</span>
                        </div>

                        <div class="elive-attendance-row">
                            <span class="elive-attendance-label">Confirmed Guests</span>
                            <span class="elive-attendance-value">{{ number_format($confirmedGuests) }}</span>
                        </div>

                        <div class="elive-attendance-row">
                            <span class="elive-attendance-label">Checked-In Guests</span>
                            <span class="elive-attendance-value" style="color:#16A34A;">{{ number_format($checkedInGuests) }}</span>
                        </div>

                        <div class="elive-attendance-row">
                            <span class="elive-attendance-label">Remaining Expected</span>
                            <span class="elive-attendance-value" style="color:#FD9618;">{{ number_format($remainingExpectedGuests) }}</span>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <div class="elive-rsvp-row-top">
                            <span class="elive-rsvp-label">Guest check-in progress</span>
                            <span class="elive-rsvp-value">{{ $guestCheckInPercent }}%</span>
                        </div>
                        <div class="elive-progress">
                            <div class="elive-progress-fill" style="width:{{ $guestCheckInPercent }}%;background:#213B73;"></div>
                        </div>
                    </div>
                </div>

                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">Recent Check-Ins</div>
                            <div class="elive-section-subtitle">Latest guest arrivals recorded at the gate</div>
                        </div>
                        @if ($scannerUrl)
                            <a href="{{ $scannerUrl }}" class="elive-link">Open Scanner</a>
                        @else
                            <span class="elive-link" style="opacity:.55;cursor:not-allowed;">
                                Select Event First
                            </span>
                        @endif
                    </div>

                    <div class="elive-table-wrap">
                        <table class="elive-checkin-table">
                            <thead>
                                <tr>
                                    <th>Invitee</th>
                                    <th>Guests</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentCheckIns->take(4) as $checkIn)
                                    @php
                                        $checkInStatus = strtolower($checkIn->status ?? 'successful');
                                        $checkInPill = in_array($checkInStatus, ['successful', 'success', 'checked_in'])
                                            ? 'pill-green'
                                            : (in_array($checkInStatus, ['failed', 'rejected']) ? 'pill-red' : 'pill-orange');
                                    @endphp
                                    <tr>
                                        <td>{{ $checkIn->invitee_name }}</td>
                                        <td>{{ number_format($checkIn->guest_count) }}</td>
                                        <td>{{ str($checkIn->method)->replace('_', ' ')->title() }}</td>
                                        <td>
                                            <span class="elive-pill {{ $checkInPill }}">
                                                {{ str($checkInStatus)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $checkIn->checked_in_at
                                                ? \Illuminate\Support\Carbon::parse($checkIn->checked_in_at)->format('H:i')
                                                : '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding:22px;text-align:center;color:#64748B;">
                                            No check-ins recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="elive-card elive-section">
                    <div class="elive-section-header">
                        <div>
                            <div class="elive-section-title">System Alerts</div>
                            <div class="elive-section-subtitle">Issues and pending actions requiring attention</div>
                        </div>
                    </div>

                    <div class="elive-alert-list">
                        @foreach (array_slice($systemAlerts, 0, 4) as $alert)
                            @php
                                $alertClass = 'alert-'.($alert['level'] ?? 'info');
                            @endphp

                            @if (! empty($alert['url']))
                                <a href="{{ $alert['url'] }}" class="elive-alert {{ $alertClass }}">
                            @else
                                <div class="elive-alert {{ $alertClass }}">
                            @endif
                                    <span class="elive-alert-dot"></span>
                                    <span>
                                        <span class="elive-alert-title">{{ $alert['title'] }}</span>
                                        <span class="elive-alert-description">{{ $alert['description'] }}</span>
                                    </span>
                            @if (! empty($alert['url']))
                                </a>
                            @else
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="elive-rsvp-summary" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Pending Photos</div>
                            <div class="elive-mini-value">{{ number_format($pendingPhotos) }}</div>
                        </div>
                        <div class="elive-mini-card">
                            <div class="elive-mini-label">Pending Wishes</div>
                            <div class="elive-mini-value">{{ number_format($pendingWishes) }}</div>
                        </div>
                    </div>
                </div>
                </section>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-top:12px;">
                <a href="{{ url('/admin/events') }}" class="elive-link">View All Events</a>
                <a href="{{ url('/admin/sms-logs') }}" class="elive-link">View Message Logs</a>
                <a href="{{ url('/admin/rsvp-report') }}" class="elive-link">Open Reports</a>
            </div>

            @endif
        </div>
    </div>
</x-filament-panels::page>