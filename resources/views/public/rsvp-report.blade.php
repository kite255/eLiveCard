<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow, noarchive">

    <title>{{ $event->title }} - RSVP Report</title>

    <style>
        :root {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --text: #111827;
            --muted: #64748B;
            --background: #F8FAFC;
            --white: #FFFFFF;
            --border: #E2E8F0;
            --green: #15803D;
            --green-bg: #DCFCE7;
            --red: #B91C1C;
            --red-bg: #FEE2E2;
            --amber: #C2410C;
            --amber-bg: #FFF7ED;
        }

        * {
            box-sizing: border-box;
        }

        html {
            color-scheme: light;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .page {
            width: min(1180px, calc(100% - 32px));
            margin: 28px auto 48px;
        }

        .hero,
        .panel,
        .stat {
            border: 1px solid var(--border);
            background: var(--white);
            box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
        }

        .hero {
            border-radius: 22px;
            overflow: hidden;
        }

        .hero-top {
            padding: 28px;
            background: var(--elive-blue);
            color: var(--white);
        }

        .hero-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .hero-title-content {
            min-width: 0;
            flex: 1 1 auto;
        }

        .hero-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }

        .hero-logo {
            display: block;
            width: auto;
            height: 46px;
            max-width: 200px;
            object-fit: contain;
            background: transparent;
            filter: none;
            opacity: 1;
            mix-blend-mode: normal;
        }

        .hero-report-label {
            display: none;
        }

        .eyebrow {
            color: #FD9618;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 8px 0 6px;
            font-size: clamp(26px, 4vw, 38px);
            line-height: 1.15;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            color: rgba(255, 255, 255, .82);
            font-size: 14px;
        }

        .hero-bottom {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
            margin: 18px 0;
        }

        .stat {
            padding: 17px;
            border-radius: 16px;
        }

        .stat strong {
            display: block;
            color: var(--elive-blue);
            font-size: 28px;
            line-height: 1;
        }

        .stat span {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }

        .panel {
            border-radius: 20px;
            overflow: hidden;
        }

        .panel-heading {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .panel-heading h2 {
            margin: 0;
            color: var(--elive-blue);
            font-size: 18px;
        }

        .panel-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .filters {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 200px auto;
            gap: 10px;
            padding: 16px 20px;
            background: #F8FAFC;
            border-bottom: 1px solid var(--border);
        }

        .input,
        .select,
        .button {
            min-height: 42px;
            border-radius: 11px;
        }

        .input,
        .select {
            width: 100%;
            border: 1px solid #CBD5E1;
            background: var(--white);
            color: var(--text);
            padding: 0 12px;
            outline: none;
        }

        .input:focus,
        .select:focus {
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 3px rgba(33, 59, 115, .12);
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--elive-blue);
            background: var(--elive-blue);
            color: var(--white);
            padding: 0 16px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .button.secondary {
            border-color: #CBD5E1;
            background: var(--white);
            color: #334155;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: middle;
            font-size: 13px;
        }

        th {
            background: #F8FAFC;
            color: #475569;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .03em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #FCFDFE;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .name {
            font-weight: 850;
            color: #0F172A;
        }

        .subtle {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 6px 9px;
            font-size: 11px;
            font-weight: 850;
            white-space: nowrap;
        }

        .badge.attending {
            background: var(--green-bg);
            color: var(--green);
        }

        .badge.not-attending {
            background: var(--red-bg);
            color: var(--red);
        }

        .badge.pending {
            background: var(--amber-bg);
            color: var(--amber);
        }

        .comment {
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty {
            padding: 44px 20px;
            text-align: center;
            color: var(--muted);
        }

        .pagination {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pagination-link,
        .pagination-current {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 34px;
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            text-decoration: none;
            line-height: 1.3;
        }

        .pagination-link {
            color: var(--elive-blue);
            background: #fff;
            font-weight: 700;
        }

        .pagination-link:hover {
            border-color: var(--elive-blue);
            background: var(--bg);
        }

        .pagination-link:focus-visible {
            outline: 2px solid var(--elive-orange);
            outline-offset: 2px;
        }

        .pagination-link.is-disabled {
            color: var(--muted);
            cursor: default;
            opacity: .65;
        }

        .pagination-current {
            color: var(--text);
            border-color: transparent;
        }

        .pagination-summary {
            text-align: center;
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
        }

        .channel-heading {
            text-align: center;
            color: var(--elive-blue);
        }

        .channel-icon-heading {
            text-align: center;
            vertical-align: middle;
            padding-top: 7px;
            padding-bottom: 8px;
        }

        .channel-icon {
            display: block;
            width: 20px;
            height: 20px;
            margin: 0 auto;
        }

        .channel-icon-whatsapp {
            color: #25D366;
        }

        .channel-icon-sms {
            color: var(--elive-blue);
        }

        @media (max-width: 760px) {
            .mobile-table-wrap .channel-icon {
                width: 18px;
                height: 18px;
            }

            .mobile-table-wrap .channel-icon-heading {
                padding: 5px 2px;
            }
        }

        .reply-value {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            overflow-wrap: anywhere;
            max-width: 240px;
            line-height: 1.4;
        }

        .reply-column-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .reply-column-label .channel-icon {
            width: 16px;
            height: 16px;
            margin: 0;
        }

        @media (max-width: 760px) {
            .mobile-table-wrap table {
                min-width: 490px;
            }

            .mobile-table-wrap .mobile-row-number {
                width: 6%;
                text-align: center;
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
                padding-left: 3px;
                padding-right: 3px;
            }

            .mobile-table-wrap .reply-value {
                max-width: 120px;
                font-size: 10px;
            }

            .mobile-table-wrap .channel-pair {
                display: flex;
                flex-direction: column;
                gap: 3px;
            }

            .mobile-table-wrap .channel-pair > span {
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            .mobile-table-wrap .channel-pair .channel-icon {
                margin: 0;
                flex: 0 0 auto;
                width: 13px;
                height: 13px;
            }

            .mobile-table-wrap .response-time {
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }
        }

        .mobile-table-wrap {
            display: none;
        }

        .delivery-status {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .delivery-status.delivered,
        .delivery-status.read {
            color: var(--green);
        }

        .delivery-status.failed {
            color: var(--red);
        }

        .delivery-status.submitted,
        .delivery-status.queued {
            color: var(--amber);
        }

        .comment-details summary {
            color: var(--elive-blue);
            font-weight: 700;
            cursor: pointer;
        }

        .comment-details p {
            white-space: normal;
            overflow-wrap: anywhere;
            margin: 6px 0 0;
            font-weight: 400;
        }

        @media (max-width: 760px) {
            .desktop-table-wrap {
                display: none;
            }

            .mobile-table-wrap {
                display: block;
                overflow-x: auto;
            }

            .mobile-table-wrap table {
                width: 100%;
                min-width: 0;
                table-layout: fixed;
            }

            .mobile-table-wrap th,
            .mobile-table-wrap td {
                padding: 9px 5px;
                font-size: 11px;
                overflow-wrap: anywhere;
                vertical-align: top;
            }

            .mobile-table-wrap th {
                font-size: 10px;
                letter-spacing: 0;
                text-transform: none;
            }

            .mobile-table-wrap .name {
                font-size: 11px;
                font-weight: 700;
            }

            .mobile-table-wrap .delivery-status {
                font-size: 10px;
                white-space: normal;
            }

            .mobile-table-wrap .subtle {
                font-size: 10px;
            }

            .mobile-table-wrap details summary {
                min-height: 36px;
                cursor: pointer;
                padding-top: 4px;
                color: var(--elive-blue);
                font-size: 10px;
            }

            .mobile-table-wrap .mobile-extra {
                margin-top: 8px;
                font-size: 11px;
                line-height: 1.6;
                overflow-wrap: anywhere;
            }
        }

        @media (max-width: 1050px) {
            .stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .page {
                width: min(100% - 20px, 1180px);
                margin-top: 12px;
            }

            .hero-top {
                padding: 22px 18px;
            }

            .hero-title-row {
                align-items: flex-start;
                gap: 14px;
            }

            .hero-logo {
                height: 34px;
                max-width: 145px;
            }

            .hero-report-label {
                text-align: left;
            }

            .hero-bottom {
                padding: 14px 18px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filters {
                grid-template-columns: 1fr;
                padding: 14px;
            }

            .panel-heading {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
    @php
        $showCardTypeColumn = (bool) ($visibleFields['card_type'] ?? false);
        $showCategoryColumn = (bool) ($visibleFields['category'] ?? false);
        $showTableColumn = (bool) ($visibleFields['table_number'] ?? false);
        $showGuestsColumn = (bool) ($visibleFields['guests'] ?? false);
        $showResponseDateColumn = (bool) ($visibleFields['response_date'] ?? false);
        $showCommentColumn = (bool) ($visibleFields['comment'] ?? false);

        $visibleColumnCount =
            7
            + ($showPhoneNumbers ? 1 : 0)
            + ($showCardTypeColumn ? 1 : 0)
            + ($showCategoryColumn ? 1 : 0)
            + ($showTableColumn ? 1 : 0)
            + ($showGuestsColumn ? 1 : 0)
;
    @endphp
    <main class="page">
        <section class="hero">
            <div class="hero-top">
                <div class="hero-title-row">
                    <div class="hero-title-content">
                        <div class="eyebrow">Secure Client RSVP Report</div>

                        <h1>{{ $event->title }}</h1>

                        <div class="hero-meta">
                            @if ($event->event_date)
                                <span>{{ $event->event_date->format('d M Y') }}</span>
                            @endif

                            @if ($event->start_time)
                                <span>{{ $event->start_time->format('h:i A') }}</span>
                            @endif

                            @if ($event->venue_name)
                                <span>{{ $event->venue_name }}</span>
                            @endif
                        </div>
                    </div>

                    <a
                        href="{{ route('home') }}"
                        class="hero-logo-wrap"
                        aria-label="eLive Card home"
                    >
                        <img
                            src="{{ asset('images/elive-cardw-logo.png') }}"
                            alt="eLive Card"
                            class="hero-logo"
                        >
                    </a>
                </div>
            </div>

            <div class="hero-bottom">
                <span>Read-only report generated by eLive Card.</span>

                @if ($event->rsvp_share_expires_at)
                    <span>
                        Link expires {{ $event->rsvp_share_expires_at->format('d M Y H:i') }}
                    </span>
                @else
                    <span>No expiry date</span>
                @endif
            </div>
        </section>

        <section class="stats">
            <div class="stat">
                <strong>{{ $total }}</strong>
                <span>Total Invitees</span>
            </div>

            <div class="stat">
                <strong>{{ $attending }}</strong>
                <span>Attending</span>
            </div>

            <div class="stat">
                <strong>{{ $notAttending }}</strong>
                <span>Not Attending</span>
            </div>

            <div class="stat">
                <strong>{{ $pending }}</strong>
                <span>Pending RSVP</span>
            </div>

            <div class="stat">
                <strong>{{ $confirmedGuests }}</strong>
                <span>Confirmed Guests</span>
            </div>

            <div class="stat">
                <strong>{{ $responseRate }}%</strong>
                <span>Response Rate</span>
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Invitee Responses</h2>
                    <p>{{ $responded }} of {{ $total }} invitees have responded.</p>
                </div>
            </div>

            <form method="GET" action="{{ request()->url() }}" class="filters">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="input"
                    placeholder="{{ $showPhoneNumbers ? 'Search name, phone, category, card type or table' : 'Search name, category, card type or table' }}"
                >

                <select name="status" class="select">
                    <option value="">All RSVP statuses</option>
                    <option value="attending" @selected($status === 'attending')>Attending</option>
                    <option value="not_attending" @selected($status === 'not_attending')>Not Attending</option>
                    <option value="declined" @selected($status === 'declined')>Declined</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="maybe" @selected($status === 'maybe')>Maybe</option>
                </select>

                <select name="sms_delivery" class="select" aria-label="SMS delivery status">
                    <option value="">All SMS delivery statuses</option>
                    <option value="not_sent" @selected($smsDelivery === 'not_sent')>Not Sent</option>
                    <option value="queued" @selected($smsDelivery === 'queued')>Queued</option>
                    <option value="submitted" @selected($smsDelivery === 'submitted')>Submitted</option>
                    <option value="delivered" @selected($smsDelivery === 'delivered')>Delivered</option>
                    <option value="failed" @selected($smsDelivery === 'failed')>Failed</option>
                    <option value="logged_only" @selected($smsDelivery === 'logged_only')>Logged Only</option>
                    <option value="unknown" @selected($smsDelivery === 'unknown')>Unknown</option>
                </select>

                <select name="whatsapp_delivery" class="select" aria-label="WhatsApp delivery status">
                    <option value="">All WhatsApp delivery statuses</option>
                    <option value="not_sent" @selected($whatsappDelivery === 'not_sent')>Not Sent</option>
                    <option value="queued" @selected($whatsappDelivery === 'queued')>Queued</option>
                    <option value="submitted" @selected($whatsappDelivery === 'submitted')>Submitted</option>
                    <option value="delivered" @selected($whatsappDelivery === 'delivered')>Delivered</option>
                    <option value="read" @selected($whatsappDelivery === 'read')>Read</option>
                    <option value="failed" @selected($whatsappDelivery === 'failed')>Failed</option>
                    <option value="logged_only" @selected($whatsappDelivery === 'logged_only')>Logged Only</option>
                    <option value="unknown" @selected($whatsappDelivery === 'unknown')>Unknown</option>
                </select>

                <div style="display:flex; gap:8px;">
                    <button type="submit" class="button">Apply Filters</button>

                    @if ($search !== '' || $status !== '' || $smsDelivery !== '' || $whatsappDelivery !== '')
                        <a href="{{ request()->url() }}" class="button secondary">
                            Clear
                        </a>
                    @endif
                </div>
            </form>

            <div class="table-wrap desktop-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Invitee</th>

                            @if ($showPhoneNumbers)
                                <th rowspan="2">Phone</th>
                            @endif

                            @if ($showCardTypeColumn)
                                <th rowspan="2">Card Type</th>
                            @endif

                            @if ($showCategoryColumn)
                                <th rowspan="2">Category</th>
                            @endif

                            @if ($showTableColumn)
                                <th rowspan="2">Table</th>
                            @endif

                            @if ($showGuestsColumn)
                                <th rowspan="2">Guests</th>
                            @endif

                            <th rowspan="2">RSVP</th>
                            <th colspan="2" scope="colgroup" class="channel-heading">CHANNEL</th>

                            <th rowspan="2">WhatsApp Reply</th>
                            <th rowspan="2">Response Time</th>
                        </tr>
                        <tr>
                            <th scope="col" class="channel-icon-heading"><svg class="channel-icon channel-icon-whatsapp" role="img" aria-label="WhatsApp" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>WhatsApp</title><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.966-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.198-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.058-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.496.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.371-.272.297-1.04 1.016-1.04 2.479s1.065 2.875 1.213 3.073c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.693.625.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.002-5.443 4.43-9.87 9.875-9.87a9.81 9.81 0 0 1 6.987 2.894 9.8 9.8 0 0 1 2.89 6.99c-.003 5.443-4.43 9.872-9.868 9.872m8.403-18.274A11.9 11.9 0 0 0 12.05 0C5.495 0 .16 5.335 .157 11.893c-.001 2.096.547 4.143 1.588 5.945L0 24l6.3-1.653a11.88 11.88 0 0 0 5.747 1.464h.005c6.557 0 11.892-5.335 11.895-11.893a11.82 11.82 0 0 0-3.493-8.413"/></svg></th>
                            <th scope="col" class="channel-icon-heading"><svg class="channel-icon channel-icon-sms" role="img" aria-label="SMS" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><title>SMS</title><rect x="2.5" y="5" width="19" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m3.5 7 8.5 6 8.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($invitees as $invitee)
                            @php
                                $rsvpStatus = $invitee->rsvp_status ?: 'pending';

                                $badgeClass = match ($rsvpStatus) {
                                    'attending' => 'attending',
                                    'not_attending', 'declined' => 'not-attending',
                                    default => 'pending',
                                };

                                $allowedGuests = max(
                                    1,
                                    (int) (
                                        $invitee->final_allowed_guests
                                        ?? $invitee->allowed_guests
                                        ?? $invitee->cardType?->allowed_guests
                                        ?? $invitee->cardType?->allowed_people
                                        ?? 1
                                    )
                                );

                                $confirmedCount = in_array(
                                    $rsvpStatus,
                                    ['not_attending', 'declined'],
                                    true
                                )
                                    ? 0
                                    : max(0, (int) ($invitee->confirmed_guests ?? 0));
                            @endphp

                            <tr>
                                <td>{{ $invitees->firstItem() + $loop->index }}</td>

                                <td>
                                    <div class="name">{{ $invitee->name }}</div>
                                </td>

                                @if ($showPhoneNumbers)
                                    <td>{{ $invitee->phone ?: '—' }}</td>
                                @endif

                                @if ($showCardTypeColumn)
                                    <td>{{ $invitee->cardType?->name ?: '—' }}</td>
                                @endif

                                @if ($showCategoryColumn)
                                    <td>{{ $invitee->category ?: '—' }}</td>
                                @endif

                                @if ($showTableColumn)
                                    <td>{{ $invitee->table_number ?: '—' }}</td>
                                @endif

                                @if ($showGuestsColumn)
                                    <td>{{ $confirmedCount }} / {{ $allowedGuests }}</td>
                                @endif

                                <td>
                                    <span class="badge {{ $badgeClass }}">
                                        {{ str($rsvpStatus)->replace('_', ' ')->title() }}
                                    </span>
                                </td>

                                <td><span class="delivery-status {{ strtolower($deliveryStatuses[$invitee->id]['whatsapp'] ?? 'not sent') }}">{{ $deliveryStatuses[$invitee->id]['whatsapp'] ?? 'Not Sent' }}</span></td>
                                <td><span class="delivery-status {{ strtolower($deliveryStatuses[$invitee->id]['sms'] ?? 'not sent') }}">{{ $deliveryStatuses[$invitee->id]['sms'] ?? 'Not Sent' }}</span></td>

                                <td>
                                    <div class="reply-value" title="{{ $invitee->last_reply_message ?: 'No reply' }}">{{ $invitee->last_reply_message ?: '—' }}</div>
                                </td>
                                <td class="response-time">{{ $invitee->rsvp_confirmed_at?->timezone('Africa/Dar_es_Salaam')->format('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $visibleColumnCount }}">
                                    <div class="empty">
                                        No invitees match the selected filters.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mobile-table-wrap">
                <table aria-label="Compact invitee RSVP, channel, reply and response time report">
                    <thead>
                        <tr>
                            <th scope="col" class="mobile-row-number">#</th>
                            <th style="width:24%">Invitee</th>
                            <th style="width:17%">RSVP</th>
                            <th style="width:22%">CHANNEL</th>
                            <th style="width:19%"><span class="reply-column-label"><svg class="channel-icon channel-icon-whatsapp" role="img" aria-label="WhatsApp" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>WhatsApp</title><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.966-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.198-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.058-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.496.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.371-.272.297-1.04 1.016-1.04 2.479s1.065 2.875 1.213 3.073c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.693.625.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.002-5.443 4.43-9.87 9.875-9.87a9.81 9.81 0 0 1 6.987 2.894 9.8 9.8 0 0 1 2.89 6.99c-.003 5.443-4.43 9.872-9.868 9.872m8.403-18.274A11.9 11.9 0 0 0 12.05 0C5.495 0 .16 5.335 .157 11.893c-.001 2.096.547 4.143 1.588 5.945L0 24l6.3-1.653a11.88 11.88 0 0 0 5.747 1.464h.005c6.557 0 11.892-5.335 11.895-11.893a11.82 11.82 0 0 0-3.493-8.413"/></svg> Reply</span></th>
                            <th style="width:12%">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invitees as $invitee)
                            @php
                                $mobileRsvp = $invitee->rsvp_status ?: 'pending';
                                $mobileStatus = match ($mobileRsvp) {
                                    'attending' => 'attending',
                                    'not_attending', 'declined' => 'not-attending',
                                    default => 'pending',
                                };
                            @endphp
                            <tr>
                                <td class="mobile-row-number">{{ $invitees->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="name">{{ $invitee->name }}</div>
                                    <div class="subtle">{{ $invitee->cardType?->name ?: '—' }}</div>
                                </td>
                                <td><span class="delivery-status {{ $mobileStatus }}">{{ str($mobileRsvp)->replace('_', ' ')->title() }}</span></td>
                                <td>
                                    <div class="channel-pair">
                                        <span><svg class="channel-icon channel-icon-whatsapp" role="img" aria-label="WhatsApp" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>WhatsApp</title><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.966-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.198-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.058-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.496.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.371-.272.297-1.04 1.016-1.04 2.479s1.065 2.875 1.213 3.073c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.693.625.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.002-5.443 4.43-9.87 9.875-9.87a9.81 9.81 0 0 1 6.987 2.894 9.8 9.8 0 0 1 2.89 6.99c-.003 5.443-4.43 9.872-9.868 9.872m8.403-18.274A11.9 11.9 0 0 0 12.05 0C5.495 0 .16 5.335 .157 11.893c-.001 2.096.547 4.143 1.588 5.945L0 24l6.3-1.653a11.88 11.88 0 0 0 5.747 1.464h.005c6.557 0 11.892-5.335 11.895-11.893a11.82 11.82 0 0 0-3.493-8.413"/></svg><span class="delivery-status {{ strtolower($deliveryStatuses[$invitee->id]['whatsapp'] ?? 'not sent') }}">{{ $deliveryStatuses[$invitee->id]['whatsapp'] ?? 'Not Sent' }}</span></span>
                                        <span><svg class="channel-icon channel-icon-sms" role="img" aria-label="SMS" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><title>SMS</title><rect x="2.5" y="5" width="19" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m3.5 7 8.5 6 8.5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="delivery-status {{ strtolower($deliveryStatuses[$invitee->id]['sms'] ?? 'not sent') }}">{{ $deliveryStatuses[$invitee->id]['sms'] ?? 'Not Sent' }}</span></span>
                                    </div>
                                </td>
                                <td><div class="reply-value" title="{{ $invitee->last_reply_message ?: 'No reply' }}">{{ $invitee->last_reply_message ?: '—' }}</div></td>
                                <td class="response-time">{{ $invitee->rsvp_confirmed_at?->timezone('Africa/Dar_es_Salaam')->format('d M H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No invitees match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invitees->hasPages())
                <nav class="pagination" aria-label="Invitee report pages">
                    <div class="pagination-controls">
                        @if ($invitees->onFirstPage())
                            <span class="pagination-link is-disabled" aria-disabled="true">Previous</span>
                        @else
                            <a class="pagination-link" rel="prev" href="{{ $invitees->previousPageUrl() }}">Previous</a>
                        @endif

                        <span class="pagination-current" aria-current="page">Page {{ $invitees->currentPage() }} of {{ $invitees->lastPage() }}</span>

                        @if ($invitees->hasMorePages())
                            <a class="pagination-link" rel="next" href="{{ $invitees->nextPageUrl() }}">Next</a>
                        @else
                            <span class="pagination-link is-disabled" aria-disabled="true">Next</span>
                        @endif
                    </div>
                    <div class="pagination-summary">
                        Showing {{ $invitees->firstItem() }} to {{ $invitees->lastItem() }} of {{ $invitees->total() }} results
                    </div>
                </nav>
            @endif
        </section>
    </main>
</body>
</html>
