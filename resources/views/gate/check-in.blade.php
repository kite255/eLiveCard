<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Check-in - {{ $event->title ?? $event->name ?? 'Event' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#213B73">

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
            --topbar-height: 74px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            width: 100%;
            min-height: 100%;
            overflow-x: hidden;
        }

        body {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding-top: var(--topbar-height);
            overflow-x: hidden;
            font-family: Inter, Arial, sans-serif;
            background: var(--bg);
            color: var(--dark);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        img,
        video,
        canvas,
        svg {
            max-width: 100%;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9980;
            min-height: var(--topbar-height);
            display: flex;
            align-items: center;
            background: var(--blue);
            color: white;
            padding: 16px;
            border-bottom: none;
            box-shadow: 0 10px 30px rgba(17, 24, 39, 0.18);
        }

        .topbar-inner {
            width: 100%;
            min-width: 0;
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
            min-width: 0;
            color: #FFFFFF;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
            width: 100%;
            min-width: 0;
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 16px 190px;
        }

        .event-card,
        .panel {
            min-width: 0;
            overflow: hidden;
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
            color: var(--blue);
            font-size: 22px;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .event-meta {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
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
            overflow: hidden;
            border-radius: 12px;
            background: #000;
        }

        #reader > div {
            width: 100% !important;
        }

        #reader video,
        #reader canvas {
            width: 100% !important;
            max-height: 520px;
            object-fit: cover;
            border-radius: 12px;
        }

        #reader video {
            border-radius: 12px;
        }

        .scanner-status {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 24px;
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .scanner-status-dot {
            width: 9px;
            height: 9px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: var(--muted);
        }

        .scanner-status.active .scanner-status-dot {
            background: var(--green);
            box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.12);
        }

        .scanner-status.warning .scanner-status-dot {
            background: var(--yellow);
        }

        .scanner-status.error .scanner-status-dot {
            background: var(--red);
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        button {
            min-height: 48px;
            border: none;
            cursor: pointer;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 800;
            touch-action: manipulation;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.62;
        }

        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 3px solid rgba(33, 59, 115, 0.22);
            outline-offset: 2px;
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
            min-height: 48px;
            padding: 12px 13px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 16px;
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
            min-width: 0;
            font-weight: 800;
            text-align: right;
            overflow-wrap: anywhere;
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
            color: var(--dark);
            font-weight: 800;
            overflow-wrap: anywhere;
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
            overflow-wrap: anywhere;
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
            overflow-wrap: anywhere;
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

        @media (max-width: 1024px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .search-box {
                flex-direction: column;
            }

            .search-box button,
            .actions button {
                width: 100%;
            }

            #reader {
                min-height: 300px;
            }
        }

        @media (max-width: 768px) {
            :root {
                --topbar-height: 66px;
            }

            .topbar {
                padding: 12px 14px;
            }

            .brand-logo {
                width: 84px;
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

            .container {
                margin-top: 14px;
                padding: 0 12px 200px;
            }

            .event-card,
            .panel {
                border-radius: 16px;
            }

            .panel {
                padding: 14px;
            }

            .actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            #reader {
                min-height: 270px;
            }

            .popup-card {
                max-width: 390px;
                border-radius: 20px;
            }

            .popup-header {
                padding: 20px 16px;
            }

            .popup-body {
                padding: 16px;
            }
        }

        @media (max-width: 600px) {
            .event-title {
                font-size: 20px;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .info-row {
                display: grid;
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .info-row span:last-child {
                text-align: left;
            }

            .sticky-checkin-summary {
                align-items: stretch;
                flex-direction: column;
            }

            .sticky-guest-select {
                width: 100%;
                min-width: 0;
            }

            .sticky-guest-select label {
                text-align: left;
            }

            .popup-grid {
                grid-template-columns: 1fr;
            }

            .popup-icon {
                width: 62px;
                height: 62px;
                font-size: 36px;
            }

            .popup-title {
                font-size: 22px;
            }

            .popup-name {
                font-size: 20px;
            }
        }

        @media (max-width: 390px) {
            :root {
                --topbar-height: 62px;
            }

            .topbar {
                padding: 10px 12px;
            }

            .brand-logo {
                width: 72px;
            }

            .brand {
                font-size: 18px;
            }

            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .event-card,
            .panel {
                padding: 12px;
            }

            #reader {
                min-height: 240px;
            }

            .sticky-checkin-bar {
                padding-left: 10px;
                padding-right: 10px;
            }

            .sticky-confirm-button {
                min-height: 50px;
                font-size: 16px;
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

            <div id="scannerStatus" class="scanner-status" aria-live="polite">
                <span class="scanner-status-dot"></span>
                <span id="scannerStatusText">Camera is not running.</span>
            </div>

            <div class="actions">
                <button
                    type="button"
                    id="startScannerButton"
                    class="btn-primary"
                    onclick="startScanner()"
                >
                    Start Scanner
                </button>

                <button
                    type="button"
                    id="stopScannerButton"
                    class="btn-light"
                    onclick="stopScanner()"
                    disabled
                >
                    Stop Scanner
                </button>
            </div>
        </div>

        <div class="panel">
            <h2 class="panel-title">Manual Search</h2>

            <form id="manualSearchForm" class="search-box">
                <input
                    type="text"
                    id="manualInput"
                    name="search"
                    placeholder="Serial, last 6 code, phone, name, or short code..."
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

        <div id="recentCheckInsList" class="recent-list">
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
                <div id="recentCheckInsEmpty" class="recent-meta">No check-ins yet.</div>
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
    let selectedInvitee = null;
    let remainingGuests = 0;
    let scannerRunning = false;
    let scannerStarting = false;
    let scannerStopping = false;
    let verificationInProgress = false;
    let checkInInProgress = false;
    let lastScannedValue = null;
    let lastScannedAt = 0;
    let verifyAbortController = null;

    const verifyUrl = @json(route('gate.check-in.verify', $event));
    const confirmUrl = @json(route('gate.check-in.confirm', $event));
    const csrfToken = @json(csrf_token());

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

    function numberValue(value, fallback = 0) {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function setScannerStatus(type, message) {
        const status = document.getElementById('scannerStatus');
        const text = document.getElementById('scannerStatusText');

        if (status) {
            status.className = `scanner-status ${type || ''}`.trim();
        }

        if (text) {
            text.innerText = message;
        }
    }

    function updateScannerButtons() {
        const startButton = document.getElementById('startScannerButton');
        const stopButton = document.getElementById('stopScannerButton');

        if (startButton) {
            startButton.disabled = scannerRunning || scannerStarting || scannerStopping;
            startButton.innerText = scannerStarting ? 'Starting...' : 'Start Scanner';
        }

        if (stopButton) {
            stopButton.disabled = !scannerRunning || scannerStopping;
            stopButton.innerText = scannerStopping ? 'Stopping...' : 'Stop Scanner';
        }
    }

    function setManualSearchBusy(busy) {
        const input = document.getElementById('manualInput');
        const button = document.querySelector('#manualSearchForm button[type="submit"]');

        if (input) {
            input.disabled = busy;
        }

        if (button) {
            button.disabled = busy;
            button.innerText = busy ? 'Searching...' : 'Search';
        }
    }

    function hideStickyCheckInBar() {
        const bar = document.getElementById('stickyCheckInBar');
        const button = document.getElementById('stickyConfirmButton');

        if (bar) {
            bar.classList.remove('active');
        }

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

        if (!bar || !invitee || !selectEl || !nameEl || !summaryEl) {
            return;
        }

        const remaining = Math.max(numberValue(invitee.remaining_guests), 0);
        const checkedIn = Math.max(numberValue(invitee.checked_in_count), 0);
        const gateLimit = Math.max(
            numberValue(invitee.gate_limit, numberValue(invitee.allowed_guests, 1)),
            1
        );

        nameEl.innerText = invitee.name || 'Selected invitee';
        summaryEl.innerText = `Remaining: ${remaining} • Checked in: ${checkedIn}/${gateLimit}`;
        selectEl.innerHTML = '';

        for (let count = 1; count <= remaining; count++) {
            const option = document.createElement('option');
            option.value = String(count);
            option.textContent = count === 1 ? '1 guest' : `${count} guests`;
            selectEl.appendChild(option);
        }

        if (button) {
            button.disabled = remaining <= 0;
            button.innerText = remaining <= 0
                ? 'Guest limit reached'
                : 'Confirm Check-in';
        }

        bar.classList.toggle('active', remaining > 0);
    }

    function clearSelection() {
        selectedInviteeId = null;
        selectedInvitee = null;
        remainingGuests = 0;
        hideStickyCheckInBar();
    }

    function showResult(type, title, message, invitee = null) {
        const box = document.getElementById('resultBox');
        const titleEl = document.getElementById('resultTitle');
        const messageEl = document.getElementById('resultMessage');
        const infoEl = document.getElementById('inviteeInfo');
        const guestControl = document.getElementById('guestControl');

        if (!box || !titleEl || !messageEl || !infoEl || !guestControl) {
            return;
        }

        box.className = `result ${type || 'error'}`;
        titleEl.innerText = title || 'Result';
        messageEl.innerText = message || '';
        infoEl.innerHTML = '';
        guestControl.style.display = 'none';

        clearSelection();

        if (!invitee) {
            return;
        }

        selectedInvitee = invitee;
        selectedInviteeId = invitee.id;
        remainingGuests = Math.max(numberValue(invitee.remaining_guests), 0);

        infoEl.innerHTML = `
            <div class="info-row"><span>Name</span><span>${escapeHtml(invitee.name)}</span></div>
            <div class="info-row"><span>Phone</span><span>${escapeHtml(invitee.phone)}</span></div>
            <div class="info-row"><span>Serial</span><span>${escapeHtml(invitee.serial_number)}</span></div>
            <div class="info-row"><span>Card Type</span><span>${escapeHtml(invitee.card_type)}</span></div>
            <div class="info-row"><span>RSVP</span><span>${escapeHtml(invitee.rsvp_status || 'pending')}</span></div>
            <div class="info-row"><span>Allowed Guests</span><span>${escapeHtml(invitee.allowed_guests)}</span></div>
            <div class="info-row"><span>Confirmed Guests</span><span>${escapeHtml(invitee.confirmed_guests ?? '-')}</span></div>
            <div class="info-row"><span>Gate Limit</span><span>${escapeHtml(invitee.gate_limit ?? invitee.allowed_guests)}</span></div>
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

    function showCheckInPopup(response) {
        const popup = document.getElementById('checkInPopup');
        const header = document.getElementById('popupHeader');
        const icon = document.getElementById('popupIcon');

        if (!popup || !header || !icon) {
            return;
        }

        const details = response.success_message || {};
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
            details.heading || response.title || 'Check-in Result';

        document.getElementById('popupMessage').innerText =
            response.message || details.body || 'Operation completed.';

        document.getElementById('popupInviteeName').innerText =
            details.invitee_name || invitee.name || '-';

        document.getElementById('popupCardType').innerText =
            details.card_type || invitee.card_type || 'N/A';

        document.getElementById('popupTableNumber').innerText =
            details.table_number || invitee.table_number || 'N/A';

        const totalCheckedIn =
            details.total_checked_in ?? invitee.checked_in_count ?? 0;

        const allowedGuests =
            details.allowed_guests ??
            invitee.gate_limit ??
            invitee.allowed_guests ??
            1;

        document.getElementById('popupCheckedIn').innerText =
            `${totalCheckedIn} / ${allowedGuests}`;

        document.getElementById('popupRemaining').innerText =
            details.remaining_guests ?? invitee.remaining_guests ?? 0;

        document.getElementById('popupCategory').innerText =
            details.category || invitee.category || 'N/A';

        document.getElementById('popupGuestsNow').innerText =
            details.guests_checked_in_now ?? '-';

        const checkedInTime =
            details.checked_in_time ||
            invitee.checked_in_at ||
            invitee.last_check_in ||
            null;

        document.getElementById('popupTime').innerText =
            checkedInTime ? `Checked in at ${checkedInTime}` : '';

        popup.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCheckInPopup() {
        const popup = document.getElementById('checkInPopup');

        if (popup) {
            popup.classList.remove('active');
        }

        document.body.style.overflow = '';
        resetForNextGuest();
    }

    function resetForNextGuest() {
        clearSelection();
        lastScannedValue = null;
        lastScannedAt = 0;

        const input = document.getElementById('manualInput');
        const resultBox = document.getElementById('resultBox');
        const inviteeInfo = document.getElementById('inviteeInfo');
        const guestControl = document.getElementById('guestControl');

        if (input) {
            input.value = '';
        }

        if (resultBox) {
            resultBox.className = 'result';
        }

        if (inviteeInfo) {
            inviteeInfo.innerHTML = '';
        }

        if (guestControl) {
            guestControl.style.display = 'none';
        }

        if (window.matchMedia('(min-width: 769px)').matches && input) {
            input.focus();
        }

        startScanner();
    }

    function normalizeSearchValue(value) {
        value = String(value || '').trim();

        if (!value) {
            return '';
        }

        try {
            if (/^https?:\/\//i.test(value)) {
                const url = new URL(value);
                const parts = url.pathname.split('/').filter(Boolean);

                return parts.at(-1) || value;
            }
        } catch (error) {
            console.warn('QR URL parsing warning:', error);
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
                ? 'The server returned an invalid response.'
                : `Server returned HTTP ${response.status}. Please sign in again or contact the administrator.`,
            debug: text,
        };
    }

    async function verifyValue(value, source = 'manual') {
        value = normalizeSearchValue(value);

        if (!value) {
            showResult(
                'error',
                'Missing Input',
                'Please scan or enter a serial number, phone, name, or short code.'
            );
            return;
        }

        if (verificationInProgress || checkInInProgress) {
            return;
        }

        verificationInProgress = true;
        setManualSearchBusy(true);

        if (verifyAbortController) {
            verifyAbortController.abort();
        }

        verifyAbortController = new AbortController();

        showResult('warning', 'Searching...', 'Please wait while we verify this invitee.');

        try {
            const response = await fetch(verifyUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: verifyAbortController.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    scanned_value: value,
                    search: value,
                    source,
                }),
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
                    status: 'warning',
                });
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                showResult(
                    'error',
                    'Connection Error',
                    'Could not verify this invitee. Check the connection and try again.'
                );
            }
        } finally {
            verificationInProgress = false;
            setManualSearchBusy(false);
        }
    }

    function manualVerify(event) {
        event.preventDefault();

        const input = document.getElementById('manualInput');
        const value = input?.value || '';

        stopScanner().finally(() => verifyValue(value, 'manual'));
    }

    async function confirmCheckIn() {
        if (!selectedInviteeId || checkInInProgress) {
            if (!selectedInviteeId) {
                showResult(
                    'error',
                    'No Invitee Selected',
                    'Please scan or search for an invitee first.'
                );
            }

            return;
        }

        const guestCountInput = document.getElementById('stickyGuestCount');
        const guestCount = Number.parseInt(guestCountInput?.value || '1', 10);

        if (
            !Number.isInteger(guestCount) ||
            guestCount < 1 ||
            guestCount > remainingGuests
        ) {
            showResult(
                'error',
                'Invalid Guest Count',
                `Guest count must be between 1 and ${remainingGuests}.`,
                selectedInvitee
            );
            return;
        }

        const confirmButton = document.getElementById('stickyConfirmButton');

        checkInInProgress = true;

        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.innerText = 'Checking in...';
        }

        try {
            const response = await fetch(confirmUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    invitee_id: selectedInviteeId,
                    guest_count: guestCount,
                }),
            });

            const data = await readJsonResponse(response);

            if (data.status === 'success') {
                hideStickyCheckInBar();
                prependRecentCheckIn(data);
                showCheckInPopup(data);
                return;
            }

            if (data.status === 'warning' && data.invitee) {
                hideStickyCheckInBar();
                showCheckInPopup({
                    ...data,
                    title: data.title || 'Card Already Used',
                    message: data.message || 'This invitation card has already been used.',
                    status: 'warning',
                });
                return;
            }

            showResult(
                data.status || 'error',
                data.title || 'Check-in Failed',
                data.message || 'Check-in could not be completed.',
                data.invitee || selectedInvitee
            );
        } catch (error) {
            showResult(
                'error',
                'Check-in Failed',
                'Could not complete check-in. Please try again.',
                selectedInvitee
            );
        } finally {
            checkInInProgress = false;

            if (confirmButton && !document.getElementById('checkInPopup')?.classList.contains('active')) {
                confirmButton.disabled = false;
                confirmButton.innerText = 'Confirm Check-in';
            }
        }
    }

    function prependRecentCheckIn(response) {
        const list = document.getElementById('recentCheckInsList');
        const details = response.success_message || {};
        const invitee = response.invitee || {};

        if (!list) {
            return;
        }

        document.getElementById('recentCheckInsEmpty')?.remove();

        const name = details.invitee_name || invitee.name || 'Invitee';
        const serial = invitee.serial_number || 'No Serial';
        const total = details.total_checked_in ?? invitee.checked_in_count ?? 1;
        const time = details.checked_in_time || new Date().toLocaleString();

        const item = document.createElement('div');
        item.className = 'recent-item';
        item.innerHTML = `
            <div class="recent-name">${escapeHtml(name)}</div>
            <div class="recent-meta">
                ${escapeHtml(serial)} · ${escapeHtml(total)} guest(s) · ${escapeHtml(time)}
            </div>
        `;

        list.prepend(item);

        while (list.children.length > 10) {
            list.lastElementChild?.remove();
        }
    }

    async function startScanner() {
        if (scannerRunning || scannerStarting || scannerStopping) {
            return;
        }

        if (typeof Html5Qrcode === 'undefined') {
            setScannerStatus('error', 'Scanner library failed to load. Use manual search.');
            showResult(
                'error',
                'Scanner Unavailable',
                'The QR scanner library did not load. Refresh the page or use manual search.'
            );
            return;
        }

        scannerStarting = true;
        updateScannerButtons();
        setScannerStatus('warning', 'Starting camera...');

        try {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode('reader', {
                    verbose: false,
                });
            }

            const viewportWidth = Math.max(
                document.documentElement.clientWidth || 0,
                window.innerWidth || 0
            );

            const qrSize = viewportWidth <= 390
                ? 200
                : viewportWidth <= 600
                    ? 225
                    : 250;

            await html5QrCode.start(
                { facingMode: { ideal: 'environment' } },
                {
                    fps: 12,
                    qrbox: {
                        width: qrSize,
                        height: qrSize,
                    },
                    aspectRatio: 1,
                    disableFlip: false,
                },
                async (decodedText) => {
                    const now = Date.now();

                    if (
                        verificationInProgress ||
                        checkInInProgress ||
                        (
                            lastScannedValue === decodedText &&
                            now - lastScannedAt < 3000
                        )
                    ) {
                        return;
                    }

                    lastScannedValue = decodedText;
                    lastScannedAt = now;

                    await stopScanner();
                    await verifyValue(decodedText, 'scanner');
                },
                () => {
                    // Frame-level decode failures are expected while scanning.
                }
            );

            scannerRunning = true;
            setScannerStatus('active', 'Camera active. Point it at the QR code.');
        } catch (error) {
            scannerRunning = false;

            const permissionDenied =
                error?.name === 'NotAllowedError' ||
                String(error).toLowerCase().includes('permission');

            setScannerStatus(
                'error',
                permissionDenied
                    ? 'Camera permission was denied.'
                    : 'Camera could not be started.'
            );

            showResult(
                'error',
                permissionDenied ? 'Camera Permission Required' : 'Camera Error',
                permissionDenied
                    ? 'Allow camera access in the browser, then press Start Scanner.'
                    : 'Could not start the camera. Use manual search or try again.'
            );
        } finally {
            scannerStarting = false;
            updateScannerButtons();
        }
    }

    async function stopScanner() {
        if (!html5QrCode || !scannerRunning || scannerStopping) {
            return;
        }

        scannerStopping = true;
        updateScannerButtons();
        setScannerStatus('warning', 'Stopping camera...');

        try {
            await html5QrCode.stop();
        } catch (error) {
            console.warn('Scanner stop warning:', error);
        } finally {
            scannerRunning = false;
            scannerStopping = false;
            updateScannerButtons();
            setScannerStatus('', 'Camera is not running.');
        }
    }

    const manualSearchForm = document.getElementById('manualSearchForm');
    const popup = document.getElementById('checkInPopup');

    manualSearchForm?.addEventListener('submit', manualVerify);

    popup?.addEventListener('click', function (event) {
        if (event.target === popup) {
            closeCheckInPopup();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && popup?.classList.contains('active')) {
            closeCheckInPopup();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopScanner();
        }
    });

    window.addEventListener('beforeunload', function () {
        verifyAbortController?.abort();

        if (html5QrCode && scannerRunning) {
            html5QrCode.stop().catch(() => {});
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        updateScannerButtons();

        if (window.matchMedia('(min-width: 769px)').matches) {
            document.getElementById('manualInput')?.focus();
        }

        startScanner();
    });
</script>

</body>
</html>