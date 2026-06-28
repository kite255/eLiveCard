<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Check-in - {{ $event->title ?? $event->name ?? 'Event' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        :root {
            --blue: #213B73;
            --orange: #FD9618;
            --dark: #111827;
            --bg: #F8FAFC;
            --white: #FFFFFF;
            --green: #16A34A;
            --red: #DC2626;
            --yellow: #F59E0B;
            --border: #E5E7EB;
            --muted: #6B7280;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--dark);
        }

        .topbar {
            background: var(--blue);
            color: white;
            padding: 16px;
            border-bottom: none;
        }

        .topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .brand-logo {
            width: 100px;
            height: auto;
            max-height: 42px;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .header-strip {
            width: 2px;
            height: 34px;
            background: rgba(255, 255, 255, 0.35);
            flex-shrink: 0;
        }

        .brand {
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
            color: #FFFFFF;
            white-space: nowrap;
        }

        .badge {
            background: rgba(255, 255, 255, 0.12);
            color: #FFFFFF;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .container {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 16px 180px;
        }

        .event-card,
        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(17, 24, 39, 0.05);
        }

        .event-card {
            padding: 16px;
            margin-bottom: 16px;
        }

        .event-title {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            color: var(--blue);
        }

        .event-meta {
            color: var(--muted);
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 16px;
        }

        .panel {
            padding: 16px;
        }

        .panel-title {
            margin: 0 0 12px;
            font-size: 17px;
            font-weight: 800;
            color: var(--blue);
        }

        #reader {
            width: 100%;
            min-height: 320px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }

        #reader video {
            border-radius: 12px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            cursor: pointer;
            border-radius: 10px;
            padding: 12px 14px;
            font-weight: 800;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-orange {
            background: var(--orange);
            color: var(--dark);
        }

        .btn-light {
            background: #EEF2FF;
            color: var(--blue);
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .search-box input,
        .guest-control input {
            width: 100%;
            padding: 12px 13px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 15px;
            outline: none;
        }

        .search-box input:focus,
        .guest-control input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(33, 59, 115, 0.10);
        }

        .result {
            display: none;
            border-radius: 12px;
            padding: 14px;
            margin-top: 12px;
            border: 1px solid var(--border);
        }

        .result.success {
            display: block;
            background: #ECFDF5;
            border-color: #BBF7D0;
        }

        .result.error {
            display: block;
            background: #FEF2F2;
            border-color: #FECACA;
        }

        .result.warning {
            display: block;
            background: #FFFBEB;
            border-color: #FDE68A;
        }

        .result-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .result.success .result-title {
            color: var(--green);
        }

        .result.error .result-title {
            color: var(--red);
        }

        .result.warning .result-title {
            color: var(--yellow);
        }

        .info-list {
            margin-top: 12px;
            display: grid;
            gap: 6px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid rgba(17, 24, 39, 0.08);
        }

        .info-row span:first-child {
            color: var(--muted);
        }

        .info-row span:last-child {
            font-weight: 800;
            text-align: right;
        }

        .guest-control {
            display: none;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(17, 24, 39, 0.08);
        }

        .guest-control label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 700;
        }

        .guest-control input {
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: 800;
        }

        .recent-list {
            display: grid;
            gap: 10px;
        }

        .recent-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            background: #FFFFFF;
        }

        .recent-name {
            font-weight: 800;
            color: var(--dark);
        }

        .recent-meta {
            color: var(--muted);
            font-size: 13px;
            margin-top: 3px;
        }

        .footer-panel {
            margin-top: 16px;
        }


        .popup-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(17, 24, 39, 0.68);
        }

        .popup-overlay.active {
            display: flex;
        }

        .popup-card {
            width: 100%;
            max-width: 430px;
            overflow: hidden;
            border-radius: 24px;
            background: #FFFFFF;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.28);
            animation: popupIn 180ms ease-out;
        }

        @keyframes popupIn {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .popup-header {
            padding: 24px 20px;
            text-align: center;
            color: #FFFFFF;
            background: var(--green);
        }

        .popup-header.warning {
            background: var(--orange);
            color: var(--dark);
        }

        .popup-header.error {
            background: var(--red);
        }

        .popup-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #FFFFFF;
            color: var(--green);
            font-size: 44px;
            font-weight: 900;
            line-height: 1;
        }

        .popup-header.warning .popup-icon {
            color: var(--orange);
        }

        .popup-header.error .popup-icon {
            color: var(--red);
        }

        .popup-title {
            margin: 0;
            font-size: 25px;
            font-weight: 900;
        }

        .popup-message {
            margin: 6px 0 0;
            font-size: 14px;
            opacity: 0.95;
        }

        .popup-body {
            padding: 18px;
        }

        .popup-name-box {
            margin-bottom: 12px;
            padding: 14px;
            border-radius: 18px;
            background: var(--bg);
            text-align: center;
        }

        .popup-label {
            margin: 0 0 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .popup-name {
            margin: 0;
            color: var(--dark);
            font-size: 22px;
            font-weight: 900;
        }

        .popup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .popup-info {
            padding: 12px;
            border-radius: 16px;
            background: var(--bg);
        }

        .popup-info span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .popup-info strong {
            display: block;
            margin-top: 3px;
            color: var(--dark);
            font-size: 16px;
            font-weight: 900;
        }

        .popup-time {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        .popup-actions {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }

        .popup-actions button {
            width: 100%;
        }


        .sticky-checkin-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9990;
            display: none;
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom));
            border-top: 1px solid rgba(17, 24, 39, 0.10);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 -18px 45px rgba(17, 24, 39, 0.18);
            backdrop-filter: blur(10px);
        }

        .sticky-checkin-bar.active {
            display: block;
        }

        .sticky-checkin-inner {
            max-width: 520px;
            margin: 0 auto;
        }

        .sticky-checkin-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px 12px;
            border-radius: 16px;
            background: var(--bg);
            border: 1px solid var(--border);
        }

        .sticky-checkin-name {
            min-width: 0;
        }

        .sticky-checkin-name strong {
            display: block;
            color: var(--dark);
            font-size: 15px;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sticky-checkin-name span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .sticky-guest-select {
            min-width: 116px;
        }

        .sticky-guest-select label {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-align: right;
        }

        .sticky-guest-select select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #FFFFFF;
            color: var(--dark);
            font-size: 15px;
            font-weight: 900;
            outline: none;
        }

        .sticky-confirm-button {
            width: 100%;
            min-height: 54px;
            border-radius: 18px;
            background: var(--orange);
            color: var(--dark);
            font-size: 18px;
            font-weight: 900;
           
        }

        .sticky-confirm-button:active {
            transform: scale(0.99);
        }

        .sticky-confirm-button:disabled {
            cursor: not-allowed;
            opacity: 0.65;
            box-shadow: none;
        }

        @media (max-width: 600px) {
            .container {
                margin-top: 14px;
                padding-left: 12px;
                padding-right: 12px;
                padding-bottom: 190px;
            }

            #reader {
                min-height: 260px;
            }

            .panel {
                padding: 14px;
            }

            .sticky-checkin-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .sticky-guest-select label {
                text-align: left;
            }
        }


        @media (max-width: 850px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .search-box {
                flex-direction: column;
            }

            button {
                width: 100%;
            }
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 14px;
            }

            .topbar-inner {
                align-items: center;
            }

            .brand-logo {
                width: 82px;
                max-height: 36px;
            }

            .header-strip {
                height: 30px;
            }

            .brand {
                font-size: 20px;
            }

            .badge {
                display: none;
            }

            .event-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="brand-wrap">
            <img
                src="{{ asset('images/elive-cardw-logo.png') }}"
                alt="eLive Card Logo"
                class="brand-logo"
                onerror="this.style.display='none';"
            >

            <div class="header-strip"></div>

            <div class="brand">Gate Check-in</div>
        </div>

        <div class="badge">Scanner</div>
    </div>
</header>

<div class="container">
    <div class="event-card">
        <h1 class="event-title">{{ $event->title ?? $event->name ?? 'Event' }}</h1>

        <div class="event-meta">
            @if(! empty($event->event_date))
                {{ \Illuminate\Support\Carbon::parse($event->event_date)->format('d M Y') }}
            @endif

            @if(! empty($event->venue_name))
                · {{ $event->venue_name }}
            @endif
        </div>
    </div>

    <div class="grid">
        <div class="panel">
            <h2 class="panel-title">QR Scanner</h2>

            <div id="reader"></div>

            <div class="actions">
                <button type="button" class="btn-primary" onclick="startScanner()">Start Scanner</button>
                <button type="button" class="btn-light" onclick="stopScanner()">Stop Scanner</button>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Manual Search</h2>

            <form id="manualSearchForm" class="search-box" onsubmit="manualVerify(event)">
                <input
                    type="text"
                    id="manualInput"
                    name="search"
                    placeholder="Serial, phone, name, short code..."
                    autocomplete="off"
                >
                <button type="submit" class="btn-orange">Search</button>
            </form>

            <div id="resultBox" class="result">
                <div class="result-title" id="resultTitle"></div>
                <div id="resultMessage"></div>

                <div class="info-list" id="inviteeInfo"></div>

                <div class="guest-control" id="guestControl">
                    <label>Check-in action</label>
                    <div class="recent-meta">
                        Use the fixed button at the bottom of the screen to confirm check-in.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel footer-panel">
        <h2 class="panel-title">Recent Check-ins</h2>

        <div class="recent-list">
            @forelse($recentCheckIns as $invitee)
                <div class="recent-item">
                    <div class="recent-name">{{ $invitee->name }}</div>
                    <div class="recent-meta">
                        {{ $invitee->serial_number ?? 'No Serial' }}
                        · {{ $invitee->checked_in_count ?? 1 }} guest(s)
                        @if($invitee->checked_in_at)
                            · {{ $invitee->checked_in_at->format('d M Y H:i') }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="recent-meta">No check-ins yet.</div>
            @endforelse
        </div>
    </div>
</div>



{{-- Sticky Confirm Check-in Bar --}}
<div id="stickyCheckInBar" class="sticky-checkin-bar" aria-live="polite">
    <div class="sticky-checkin-inner">
        <div class="sticky-checkin-summary">
            <div class="sticky-checkin-name">
                <strong id="stickyInviteeName">No invitee selected</strong>
                <span id="stickyInviteeSummary">Scan or search an invitee first.</span>
            </div>

            <div class="sticky-guest-select">
                <label for="stickyGuestCount">Guests</label>
                <select id="stickyGuestCount">
                    <option value="1">1 guest</option>
                </select>
            </div>
        </div>

        <button
            type="button"
            id="stickyConfirmButton"
            class="sticky-confirm-button"
            onclick="confirmCheckIn()"
        >
            Confirm Check-in
        </button>
    </div>
</div>

{{-- Check-in Success Popup --}}
<div id="checkInPopup" class="popup-overlay" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
    <div class="popup-card">
        <div id="popupHeader" class="popup-header">
            <div id="popupIcon" class="popup-icon">✓</div>
            <h2 id="popupTitle" class="popup-title">Check-in Successful</h2>
            <p id="popupMessage" class="popup-message">Invitee checked in successfully.</p>
        </div>

        <div class="popup-body">
            <div class="popup-name-box">
                <p class="popup-label">Invitee Name</p>
                <p id="popupInviteeName" class="popup-name">-</p>
            </div>

            <div class="popup-grid">
                <div class="popup-info">
                    <span>Card Type</span>
                    <strong id="popupCardType">-</strong>
                </div>

                <div class="popup-info">
                    <span>Table</span>
                    <strong id="popupTableNumber">-</strong>
                </div>

                <div class="popup-info">
                    <span>Checked In</span>
                    <strong id="popupCheckedIn">-</strong>
                </div>

                <div class="popup-info">
                    <span>Remaining</span>
                    <strong id="popupRemaining">-</strong>
                </div>

                <div class="popup-info">
                    <span>Category</span>
                    <strong id="popupCategory">-</strong>
                </div>

                <div class="popup-info">
                    <span>Guests Now</span>
                    <strong id="popupGuestsNow">-</strong>
                </div>
            </div>

            <p id="popupTime" class="popup-time">-</p>

            <div class="popup-actions">
                <button type="button" class="btn-orange" onclick="closeCheckInPopup()">
                    Continue Scanning
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let html5QrCode = null;
    let selectedInviteeId = null;
    let remainingGuests = 0;
    let scannerRunning = false;
    let lastScannedValue = null;

    const verifyUrl = "{{ route('gate.check-in.verify', $event) }}";
    const confirmUrl = "{{ route('gate.check-in.confirm', $event) }}";
    const csrfToken = "{{ csrf_token() }}";

    function escapeHtml(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function hideStickyCheckInBar() {
        const bar = document.getElementById('stickyCheckInBar');

        if (bar) {
            bar.classList.remove('active');
        }

        const button = document.getElementById('stickyConfirmButton');

        if (button) {
            button.disabled = false;
            button.innerText = 'Confirm Check-in';
        }
    }

    function showStickyCheckInBar(invitee) {
        const bar = document.getElementById('stickyCheckInBar');
        const nameEl = document.getElementById('stickyInviteeName');
        const summaryEl = document.getElementById('stickyInviteeSummary');
        const selectEl = document.getElementById('stickyGuestCount');
        const button = document.getElementById('stickyConfirmButton');

        if (!bar || !invitee || !selectEl) {
            return;
        }

        const remaining = Math.max(Number(invitee.remaining_guests || 0), 0);
        const checkedIn = Number(invitee.checked_in_count || 0);
        const gateLimit = Number(invitee.gate_limit || invitee.allowed_guests || 1);

        nameEl.innerText = invitee.name || 'Selected invitee';
        summaryEl.innerText = `Remaining: ${remaining} • Checked in: ${checkedIn}/${gateLimit}`;

        selectEl.innerHTML = '';

        for (let i = 1; i <= remaining; i++) {
            const option = document.createElement('option');
            option.value = i;
            option.textContent = i === 1 ? '1 guest' : `${i} guests`;
            selectEl.appendChild(option);
        }

        if (button) {
            button.disabled = remaining <= 0;
            button.innerText = 'Confirm Check-in';
        }

        if (remaining > 0) {
            bar.classList.add('active');
        } else {
            bar.classList.remove('active');
        }
    }

    function showResult(type, title, message, invitee = null) {
        const box = document.getElementById('resultBox');
        const titleEl = document.getElementById('resultTitle');
        const messageEl = document.getElementById('resultMessage');
        const infoEl = document.getElementById('inviteeInfo');
        const guestControl = document.getElementById('guestControl');

        box.className = 'result ' + type;
        titleEl.innerText = title;
        messageEl.innerText = message;
        infoEl.innerHTML = '';
        guestControl.style.display = 'none';

        selectedInviteeId = null;
        remainingGuests = 0;
        hideStickyCheckInBar();

        if (invitee) {
            selectedInviteeId = invitee.id;
            remainingGuests = Number(invitee.remaining_guests || 0);

            infoEl.innerHTML = `
                <div class="info-row"><span>Name</span><span>${escapeHtml(invitee.name)}</span></div>
                <div class="info-row"><span>Phone</span><span>${escapeHtml(invitee.phone)}</span></div>
                <div class="info-row"><span>Serial</span><span>${escapeHtml(invitee.serial_number)}</span></div>
                <div class="info-row"><span>Card Type</span><span>${escapeHtml(invitee.card_type)}</span></div>
                <div class="info-row"><span>RSVP</span><span>${escapeHtml(invitee.rsvp_status || 'pending')}</span></div>
                <div class="info-row"><span>Allowed Guests</span><span>${escapeHtml(invitee.allowed_guests)}</span></div>
                <div class="info-row"><span>Confirmed Guests</span><span>${escapeHtml(invitee.confirmed_guests ?? '-')}</span></div>
                <div class="info-row"><span>Gate Limit</span><span>${escapeHtml(invitee.gate_limit || invitee.allowed_guests)}</span></div>
                <div class="info-row"><span>Checked In</span><span>${escapeHtml(invitee.checked_in_count)}</span></div>
                <div class="info-row"><span>Remaining</span><span>${escapeHtml(invitee.remaining_guests)}</span></div>
                <div class="info-row"><span>Table</span><span>${escapeHtml(invitee.table_number)}</span></div>
                <div class="info-row"><span>Category</span><span>${escapeHtml(invitee.category)}</span></div>
            `;

            if (type === 'success' && remainingGuests > 0) {
                guestControl.style.display = 'block';
                showStickyCheckInBar(invitee);
            }
        }
    }


    function showCheckInPopup(response) {
        const popup = document.getElementById('checkInPopup');
        const header = document.getElementById('popupHeader');
        const icon = document.getElementById('popupIcon');

        const success = response.success_message || {};
        const invitee = response.invitee || {};
        const status = response.status || 'success';

        header.className = 'popup-header';

        if (status === 'warning') {
            header.classList.add('warning');
            icon.innerText = '!';
        } else if (status === 'error') {
            header.classList.add('error');
            icon.innerText = '×';
        } else {
            icon.innerText = '✓';
        }

        document.getElementById('popupTitle').innerText =
            success.heading || response.title || 'Check-in Result';

        document.getElementById('popupMessage').innerText =
            response.message || success.body || 'Operation completed.';

        document.getElementById('popupInviteeName').innerText =
            success.invitee_name || invitee.name || '-';

        document.getElementById('popupCardType').innerText =
            success.card_type || invitee.card_type || 'N/A';

        document.getElementById('popupTableNumber').innerText =
            success.table_number || invitee.table_number || 'N/A';

        const totalCheckedIn = success.total_checked_in ?? invitee.checked_in_count ?? 0;
        const allowedGuests = success.allowed_guests ?? invitee.allowed_guests ?? 1;

        document.getElementById('popupCheckedIn').innerText =
            totalCheckedIn + ' / ' + allowedGuests;

        document.getElementById('popupRemaining').innerText =
            success.remaining_guests ?? invitee.remaining_guests ?? 0;

        document.getElementById('popupCategory').innerText =
            success.category || invitee.category || 'N/A';

        document.getElementById('popupGuestsNow').innerText =
            success.guests_checked_in_now ?? '-';

        const checkedInTime = success.checked_in_time || invitee.checked_in_at || invitee.last_check_in || null;

        document.getElementById('popupTime').innerText =
            checkedInTime ? 'Checked in at ' + checkedInTime : '';

        popup.classList.add('active');
    }

    function closeCheckInPopup() {
        const popup = document.getElementById('checkInPopup');

        popup.classList.remove('active');

        selectedInviteeId = null;
        remainingGuests = 0;
        lastScannedValue = null;

        const manualInput = document.getElementById('manualInput');

        if (manualInput) {
            manualInput.value = '';
            manualInput.focus();
        }

        const resultBox = document.getElementById('resultBox');
        const inviteeInfo = document.getElementById('inviteeInfo');
        const guestControl = document.getElementById('guestControl');

        resultBox.className = 'result';
        inviteeInfo.innerHTML = '';
        guestControl.style.display = 'none';
        hideStickyCheckInBar();

        startScanner();
    }

    function normalizeSearchValue(value) {
        value = String(value || '').trim();

        if (!value) {
            return '';
        }

        // QR scanners may return a full URL such as /gate/verify/{token} or /i/{shortCode}.
        // Keep manual serial/phone/name unchanged, but extract the useful last URL segment when needed.
        try {
            if (value.startsWith('http://') || value.startsWith('https://')) {
                const url = new URL(value);
                const parts = url.pathname.split('/').filter(Boolean);

                if (parts.length > 0) {
                    return parts[parts.length - 1];
                }
            }
        } catch (error) {
            // Ignore URL parsing errors and use the original value.
        }

        return value;
    }

    async function readJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return await response.json();
        }

        const text = await response.text();

        return {
            status: 'error',
            title: response.ok ? 'Invalid Response' : 'Request Failed',
            message: response.ok
                ? 'The server did not return JSON. Please check the gate verify route.'
                : `Server returned HTTP ${response.status}. Please check route, login session, or controller response.`,
            debug: text,
        };
    }

    async function verifyValue(value, source = 'manual') {
        value = normalizeSearchValue(value);

        if (!value) {
            showResult('error', 'Missing Input', 'Please scan or enter a serial number, phone, name, or short code.');
            return;
        }

        showResult('warning', 'Searching...', 'Please wait while we verify this invitee.');

        try {
            const response = await fetch(verifyUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    scanned_value: value,
                    search: value,
                    source: source,
                })
            });

            const data = await readJsonResponse(response);

            showResult(
                data.status || 'error',
                data.title || 'Result',
                data.message || '',
                data.invitee || null
            );

            if (data.status === 'warning' && data.invitee) {
                showCheckInPopup({
                    ...data,
                    title: data.title || 'Card Already Used',
                    message: data.message || 'This invitation card has already been used.',
                    status: 'warning'
                });
            }
        } catch (error) {
            showResult('error', 'Connection Error', 'Could not verify this invitee. Please check your internet connection and try again.');
        }
    }

    function manualVerify(event = null) {
        if (event) {
            event.preventDefault();
        }

        const input = document.getElementById('manualInput');
        const value = input ? input.value : '';

        verifyValue(value, 'manual');
    }

    async function confirmCheckIn() {
        if (!selectedInviteeId) {
            showResult('error', 'No Invitee Selected', 'Please scan or search for an invitee first.');
            return;
        }

        const guestCountInput = document.getElementById('stickyGuestCount');
        const guestCount = parseInt((guestCountInput && guestCountInput.value) || '1');

        if (guestCount < 1 || guestCount > remainingGuests) {
            showResult('error', 'Invalid Guest Count', `Guest count must be between 1 and ${remainingGuests}.`);
            return;
        }

        const confirmButton = document.getElementById('stickyConfirmButton');

        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.innerText = 'Checking in...';
        }

        try {
            const response = await fetch(confirmUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    invitee_id: selectedInviteeId,
                    guest_count: guestCount
                })
            });

            const data = await response.json();

            if (data.status === 'success') {
                hideStickyCheckInBar();
                showCheckInPopup(data);
                return;
            }

            if (data.status === 'warning' && data.invitee) {
                hideStickyCheckInBar();
                showCheckInPopup({
                    ...data,
                    title: data.title || 'Card Already Used',
                    message: data.message || 'This invitation card has already been used.',
                    status: 'warning'
                });
                return;
            }

            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.innerText = 'Confirm Check-in';
            }

            showResult(
                data.status || 'error',
                data.title || 'Check-in Failed',
                data.message || 'Check-in could not be completed.',
                data.invitee || null
            );
        } catch (error) {
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.innerText = 'Confirm Check-in';
            }

            showResult('error', 'Check-in Failed', 'Could not complete check-in. Please try again.');
        }
    }

    async function startScanner() {
        if (scannerRunning) {
            return;
        }

        html5QrCode = new Html5Qrcode("reader");

        const config = {
            fps: 10,
            qrbox: {
                width: 250,
                height: 250
            }
        };

        try {
            await html5QrCode.start(
                { facingMode: "environment" },
                config,
                async (decodedText) => {
                    if (lastScannedValue === decodedText) {
                        return;
                    }

                    lastScannedValue = decodedText;

                    await stopScanner();
                    await verifyValue(decodedText, 'scanner');
                }
            );

            scannerRunning = true;
        } catch (error) {
            showResult('error', 'Camera Error', 'Could not start camera. Use manual search instead.');
        }
    }

    async function stopScanner() {
        if (html5QrCode && scannerRunning) {
            await html5QrCode.stop();
            scannerRunning = false;
        }
    }

    const manualSearchForm = document.getElementById('manualSearchForm');
    const manualInput = document.getElementById('manualInput');

    if (manualSearchForm) {
        manualSearchForm.addEventListener('submit', manualVerify);
    }

    if (manualInput) {
        manualInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                manualVerify(event);
            }
        });
    }
</script>

</body>
</html>