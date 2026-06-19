<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Check-in - {{ $event->title }}</title>
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
            padding: 16px 20px;
        }

        .topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
        }

        .subtitle {
            font-size: 13px;
            opacity: .9;
            margin-top: 2px;
        }

        .badge {
            background: var(--orange);
            color: var(--dark);
            padding: 7px 13px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
        }

        .container {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .event-card,
        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(17, 24, 39, 0.06);
        }

        .event-card {
            padding: 20px;
            margin-bottom: 18px;
        }

        .event-title {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 900;
            color: var(--blue);
        }

        .event-meta {
            color: var(--muted);
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 18px;
        }

        .panel {
            padding: 18px;
        }

        .panel-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 900;
            color: var(--blue);
        }

        #reader {
            width: 100%;
            min-height: 320px;
            border-radius: 14px;
            overflow: hidden;
            background: #000;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            cursor: pointer;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 900;
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
            margin-bottom: 16px;
        }

        .search-box input,
        .guest-control input {
            width: 100%;
            padding: 13px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-size: 15px;
        }

        .result {
            display: none;
            border-radius: 16px;
            padding: 16px;
            margin-top: 14px;
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
            font-size: 21px;
            font-weight: 900;
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
            margin-top: 14px;
            display: grid;
            gap: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(17, 24, 39, 0.08);
        }

        .info-row span:first-child {
            color: var(--muted);
        }

        .info-row span:last-child {
            font-weight: 900;
            text-align: right;
        }

        .guest-control {
            display: none;
            margin-top: 16px;
            padding-top: 14px;
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
            border-radius: 14px;
            padding: 12px;
            background: #FFFFFF;
        }

        .recent-name {
            font-weight: 900;
            color: var(--dark);
        }

        .recent-meta {
            color: var(--muted);
            font-size: 13px;
            margin-top: 3px;
        }

        .footer-panel {
            margin-top: 18px;
        }

        @media (max-width: 850px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-box {
                flex-direction: column;
            }

            button {
                width: 100%;
            }

            .event-title {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-inner">
        <div>
            <div class="brand">eLive Card</div>
            <div class="subtitle">Gate Check-in</div>
        </div>
        <div class="badge">Scanner Mode</div>
    </div>
</div>

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

            <div class="search-box">
                <input
                    type="text"
                    id="manualInput"
                    placeholder="Serial number, phone, name..."
                    autocomplete="off"
                >
                <button type="button" class="btn-orange" onclick="manualVerify()">Search</button>
            </div>

            <div id="resultBox" class="result">
                <div class="result-title" id="resultTitle"></div>
                <div id="resultMessage"></div>

                <div class="info-list" id="inviteeInfo"></div>

                <div class="guest-control" id="guestControl">
                    <label for="guestCount">Number of guests entering now</label>
                    <input type="number" min="1" value="1" id="guestCount">
                    <button type="button" class="btn-primary" onclick="confirmCheckIn()">Confirm Check-in</button>
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
        if (value === null || value === undefined) {
            return '-';
        }

        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
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

        if (invitee) {
            selectedInviteeId = invitee.id;
            remainingGuests = Number(invitee.remaining_guests || 0);

            infoEl.innerHTML = `
                <div class="info-row"><span>Name</span><span>${escapeHtml(invitee.name)}</span></div>
                <div class="info-row"><span>Phone</span><span>${escapeHtml(invitee.phone)}</span></div>
                <div class="info-row"><span>Serial</span><span>${escapeHtml(invitee.serial_number)}</span></div>
                <div class="info-row"><span>Card Type</span><span>${escapeHtml(invitee.card_type)}</span></div>
                <div class="info-row"><span>Allowed Guests</span><span>${escapeHtml(invitee.allowed_guests)}</span></div>
                <div class="info-row"><span>Checked In</span><span>${escapeHtml(invitee.checked_in_count)}</span></div>
                <div class="info-row"><span>Remaining</span><span>${escapeHtml(invitee.remaining_guests)}</span></div>
                <div class="info-row"><span>Table</span><span>${escapeHtml(invitee.table_number)}</span></div>
                <div class="info-row"><span>Category</span><span>${escapeHtml(invitee.category)}</span></div>
            `;

            if (type === 'success' && remainingGuests > 0) {
                guestControl.style.display = 'block';

                const guestCountInput = document.getElementById('guestCount');
                guestCountInput.value = 1;
                guestCountInput.max = remainingGuests;
            }
        }
    }

    async function verifyValue(value) {
        value = String(value || '').trim();

        if (!value) {
            showResult('error', 'Missing Input', 'Please scan or enter a value.');
            return;
        }

        try {
            const response = await fetch(verifyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    scanned_value: value
                })
            });

            const data = await response.json();

            showResult(
                data.status || 'error',
                data.title || 'Result',
                data.message || '',
                data.invitee || null
            );
        } catch (error) {
            showResult('error', 'Connection Error', 'Could not verify this card. Please try again.');
        }
    }

    function manualVerify() {
        const value = document.getElementById('manualInput').value.trim();
        verifyValue(value);
    }

    async function confirmCheckIn() {
        if (!selectedInviteeId) {
            showResult('error', 'No Invitee Selected', 'Please scan or search for an invitee first.');
            return;
        }

        const guestCount = parseInt(document.getElementById('guestCount').value || '1');

        if (guestCount < 1 || guestCount > remainingGuests) {
            showResult('error', 'Invalid Guest Count', `Guest count must be between 1 and ${remainingGuests}.`);
            return;
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

            showResult(
                data.status || 'success',
                data.title || 'Checked In',
                data.message || 'Check-in completed.'
            );

            setTimeout(() => {
                window.location.reload();
            }, 900);
        } catch (error) {
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
                    await verifyValue(decodedText);
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

    document.getElementById('manualInput').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            manualVerify();
        }
    });
</script>

</body>
</html>