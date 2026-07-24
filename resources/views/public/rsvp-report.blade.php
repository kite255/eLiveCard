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
            padding: 16px 20px;
            border-top: 1px solid var(--border);
        }

        .pagination nav > div:first-child {
            margin-bottom: 10px;
        }

        .pagination a,
        .pagination span {
            border-radius: 8px !important;
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
            3
            + ($showPhoneNumbers ? 1 : 0)
            + ($showCardTypeColumn ? 1 : 0)
            + ($showCategoryColumn ? 1 : 0)
            + ($showTableColumn ? 1 : 0)
            + ($showGuestsColumn ? 1 : 0)
            + ($showResponseDateColumn ? 1 : 0)
            + ($showCommentColumn ? 1 : 0);
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
                    placeholder="{{ $showPhoneNumbers ? 'Search name, phone or category' : 'Search name or category' }}"
                >

                <select name="status" class="select">
                    <option value="">All RSVP statuses</option>
                    <option value="attending" @selected($status === 'attending')>Attending</option>
                    <option value="not_attending" @selected($status === 'not_attending')>Not Attending</option>
                    <option value="declined" @selected($status === 'declined')>Declined</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="maybe" @selected($status === 'maybe')>Maybe</option>
                </select>

                <div style="display:flex; gap:8px;">
                    <button type="submit" class="button">Apply Filters</button>

                    @if ($search !== '' || $status !== '')
                        <a href="{{ request()->url() }}" class="button secondary">
                            Clear
                        </a>
                    @endif
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invitee</th>

                            @if ($showPhoneNumbers)
                                <th>Phone</th>
                            @endif

                            @if ($showCardTypeColumn)
                                <th>Card Type</th>
                            @endif

                            @if ($showCategoryColumn)
                                <th>Category</th>
                            @endif

                            @if ($showTableColumn)
                                <th>Table</th>
                            @endif

                            @if ($showGuestsColumn)
                                <th>Guests</th>
                            @endif

                            <th>RSVP</th>

                            @if ($showResponseDateColumn)
                                <th>Response Date</th>
                            @endif

                            @if ($showCommentColumn)
                                <th>Comment</th>
                            @endif
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

                                @if ($showResponseDateColumn)
                                    <td>
                                        {{ $invitee->rsvp_confirmed_at?->format('d M Y H:i') ?? '—' }}
                                    </td>
                                @endif

                                @if ($showCommentColumn)
                                    <td>
                                        <div
                                            class="comment"
                                            title="{{ $invitee->last_reply_message ?: 'No comment' }}"
                                        >
                                            {{ $invitee->last_reply_message ?: '—' }}
                                        </div>
                                    </td>
                                @endif
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

            @if ($invitees->hasPages())
                <div class="pagination">
                    {{ $invitees->links() }}
                </div>
            @endif
        </section>
    </main>
</body>
</html>
