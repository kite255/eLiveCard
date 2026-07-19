<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">
    <title>{{ $event->display_name }} RSVP Report</title>

    <style>
        :root{
            --blue:#213B73;
            --orange:#FD9618;
            --dark:#111827;
            --bg:#F8FAFC;
            --border:#E5E7EB;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--dark);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .container{width:min(1180px,calc(100% - 32px));margin:32px auto}
        .panel{overflow:hidden;border:1px solid var(--border);border-radius:22px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.05)}
        .header{padding:22px;background:var(--blue);color:#fff}
        .header-row{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}
        .title{margin:0;font-size:24px;font-weight:900}
        .sub{margin-top:6px;color:rgba(255,255,255,.76);font-size:13px}
        .updated{text-align:right;font-size:12px;color:rgba(255,255,255,.76)}
        .stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;padding:18px}
        .stat{border:1px solid var(--border);border-radius:15px;padding:14px}
        .value{font-size:26px;font-weight:900}
        .label{margin-top:5px;color:#64748B;font-size:12px;font-weight:800}
        .filters{display:grid;grid-template-columns:minmax(220px,1fr) 200px auto;gap:10px;padding:0 18px 18px}
        input,select,button{height:42px;border-radius:11px;font:inherit}
        input,select{width:100%;border:1px solid #CBD5E1;background:#fff;padding:0 13px}
        button{border:0;background:var(--blue);padding:0 18px;color:#fff;font-weight:800;cursor:pointer}
        .table-wrap{margin:0 18px 18px;overflow-x:auto;border:1px solid var(--border);border-radius:16px}
        table{width:100%;min-width:850px;border-collapse:collapse}
        th,td{padding:12px 13px;border-bottom:1px solid var(--border);text-align:left;font-size:13px}
        th{background:#F8FAFC;font-size:11px;text-transform:uppercase;letter-spacing:.03em}
        tr:last-child td{border-bottom:0}
        .badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:850}
        .attending{background:#DCFCE7;color:#15803D}
        .not-attending{background:#FEE2E2;color:#B91C1C}
        .pending{background:#FFF7ED;color:#C2410C}
        .pagination{padding:0 18px 18px}
        .privacy{padding:0 18px 18px;color:#64748B;font-size:12px}
        @media(max-width:850px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.filters{grid-template-columns:1fr}.header-row{flex-direction:column}.updated{text-align:left}}
    </style>
</head>
<body>
    <main class="container">
        <section class="panel">
            <header class="header">
                <div class="header-row">
                    <div>
                        <h1 class="title">{{ $event->display_name }}</h1>
                        <div class="sub">
                            RSVP progress report · {{ $event->event_date_display }} · {{ $event->venue_display }}
                        </div>
                    </div>

                    <div class="updated">
                        Automatically refreshes every 60 seconds<br>
                        Last loaded: {{ now()->format('d M Y H:i:s') }}
                    </div>
                </div>
            </header>

            <section class="stats">
                <div class="stat"><div class="value">{{ number_format($total) }}</div><div class="label">Total Invitees</div></div>
                <div class="stat"><div class="value" style="color:#15803D">{{ number_format($attending) }}</div><div class="label">Attending</div></div>
                <div class="stat"><div class="value" style="color:#B91C1C">{{ number_format($notAttending) }}</div><div class="label">Not Attending</div></div>
                <div class="stat"><div class="value" style="color:#FD9618">{{ number_format($pending) }}</div><div class="label">Pending</div></div>
                <div class="stat"><div class="value" style="color:#213B73">{{ $responseRate }}%</div><div class="label">Response Rate</div></div>
            </section>

            <form method="GET" class="filters">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search invitee or serial number"
                >

                <select name="status">
                    <option value="">All RSVP statuses</option>
                    <option value="attending" @selected($status === 'attending')>Attending</option>
                    <option value="not_attending" @selected($status === 'not_attending')>Not Attending</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                </select>

                <button type="submit">Apply Filters</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invitee</th>
                            @if ($event->rsvp_share_show_phone)
                                <th>Phone</th>
                            @endif
                            <th>Card Type</th>
                            <th>Allowed</th>
                            <th>Confirmed</th>
                            <th>RSVP</th>
                            <th>Response Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invitees as $invitee)
                            @php
                                $statusValue = $invitee->rsvp_status ?: 'pending';
                                $statusClass = in_array($statusValue, ['not_attending', 'declined'], true)
                                    ? 'not-attending'
                                    : ($statusValue === 'attending' ? 'attending' : 'pending');

                                $allowedGuests = (int) (
                                    $invitee->final_allowed_guests
                                    ?? $invitee->allowed_guests
                                    ?? $invitee->cardType?->allowed_guests
                                    ?? $invitee->cardType?->allowed_people
                                    ?? 1
                                );

                                $confirmed = in_array($statusValue, ['not_attending', 'declined'], true)
                                    ? 0
                                    : (int) ($invitee->confirmed_guests ?? 0);
                            @endphp

                            <tr>
                                <td>{{ $invitees->firstItem() + $loop->index }}</td>
                                <td>
                                    <strong>{{ $invitee->name }}</strong><br>
                                    <small style="color:#64748B">{{ $invitee->serial_number }}</small>
                                </td>
                                @if ($event->rsvp_share_show_phone)
                                    <td>{{ $invitee->phone }}</td>
                                @endif
                                <td>{{ $invitee->cardType?->name ?? 'Unassigned' }}</td>
                                <td>{{ $allowedGuests }}</td>
                                <td>{{ $confirmed }}</td>
                                <td>
                                    <span class="badge {{ $statusClass }}">
                                        {{ str($statusValue)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td>{{ $invitee->rsvp_confirmed_at?->format('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $event->rsvp_share_show_phone ? 8 : 7 }}" style="text-align:center;padding:36px">
                                    No invitees match the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $invitees->links() }}
            </div>

            <div class="privacy">
                This is a private, read-only RSVP report. Administrative controls and message-delivery details are not included.
                @if ($latestResponseAt)
                    Latest RSVP update: {{ \Illuminate\Support\Carbon::parse($latestResponseAt)->format('d M Y H:i') }}.
                @endif
            </div>
        </section>
    </main>
</body>
</html>
