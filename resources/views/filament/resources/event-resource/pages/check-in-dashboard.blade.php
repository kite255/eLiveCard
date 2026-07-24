<x-filament-panels::page>
    <style>
        .elive-checkin {
            --blue: #213B73;
            --orange: #FD9618;
            --text: #111827;
            --muted: #64748B;
            --border: #E2E8F0;
            --surface: #FFFFFF;
            width: 100%;
            min-width: 0;
            display: grid;
            gap: 20px;
            overflow-x: hidden;
        }

        .elive-checkin,
        .elive-checkin *,
        .elive-checkin *::before,
        .elive-checkin *::after {
            box-sizing: border-box;
        }

        .elive-checkin img,
        .elive-checkin svg,
        .elive-checkin video,
        .elive-checkin canvas {
            max-width: 100%;
        }

        .elive-checkin input,
        .elive-checkin select,
        .elive-checkin textarea,
        .elive-checkin button,
        .elive-checkin a {
            max-width: 100%;
        }

        .elive-hero,
        .elive-panel,
        .elive-stat {
            min-width: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
        }

        .elive-hero {
            padding: 24px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .elive-title {
            margin: 0;
            color: var(--blue);
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .elive-subtitle {
            margin: 7px 0 0;
            color: var(--muted);
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .elive-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .elive-stat {
            padding: 18px;
        }

        .elive-stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .elive-stat-value {
            margin-top: 7px;
            color: var(--text);
            font-size: 28px;
            font-weight: 800;
        }

        .elive-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
            gap: 20px;
            align-items: start;
        }

        .elive-panel {
            padding: 22px;
        }

        .elive-panel h2 {
            margin: 0;
            color: var(--blue);
            font-size: 18px;
            font-weight: 800;
        }

        .elive-panel-note {
            margin: 5px 0 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .elive-search {
            display: flex;
            gap: 10px;
        }

        .elive-input {
            width: 100%;
            min-height: 48px;
            border: 1px solid #CBD5E1;
            border-radius: 12px;
            padding: 0 14px;
            background: #FFFFFF;
            color: var(--text);
            font-size: 16px;
            outline: none;
        }

        .elive-input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(33, 59, 115, .12);
        }

        .elive-result {
            margin-top: 12px;
            width: 100%;
            min-height: 72px;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            background: #FFFFFF;
            text-align: left;
            cursor: pointer;
            overflow-wrap: anywhere;
            touch-action: manipulation;
        }

        .elive-result:hover {
            border-color: var(--blue);
            background: #F8FAFC;
        }

        .elive-result-name {
            color: var(--text);
            font-weight: 800;
        }

        .elive-result-meta {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }

        .elive-badges {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .elive-badge {
            padding: 5px 9px;
            border-radius: 999px;
            background: #F1F5F9;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .elive-guest-card {
            display: grid;
            gap: 14px;
        }

        .elive-detail {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding-bottom: 11px;
            border-bottom: 1px solid #F1F5F9;
        }

        .elive-detail span:first-child {
            color: var(--muted);
        }

        .elive-detail span:last-child {
            min-width: 0;
            color: var(--text);
            font-weight: 750;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .elive-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .elive-button {
            min-height: 48px;
            border: 0;
            border-radius: 12px;
            padding: 0 18px;
            font-weight: 750;
            cursor: pointer;
            touch-action: manipulation;
        }

        .elive-button:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .elive-button-primary {
            background: var(--blue);
            color: #FFFFFF;
        }

        .elive-button-secondary {
            background: #F1F5F9;
            color: var(--text);
        }

        .elive-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .elive-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .elive-table th,
        .elive-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #EEF2F7;
            text-align: left;
            font-size: 14px;
        }

        .elive-table th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .elive-empty {
            padding: 30px 12px;
            color: var(--muted);
            text-align: center;
        }

        .elive-mobile-activity {
            display: none;
        }

        .elive-activity-card {
            display: grid;
            gap: 9px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #FFFFFF;
        }

        .elive-activity-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }

        .elive-activity-name {
            color: var(--text);
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .elive-activity-time {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .elive-activity-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .elive-activity-meta div {
            padding: 9px 10px;
            border-radius: 10px;
            background: #F8FAFC;
        }

        .elive-activity-meta small {
            display: block;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .elive-activity-meta strong {
            display: block;
            margin-top: 3px;
            color: var(--text);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1200px) {
            .elive-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .elive-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-hero {
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            .elive-checkin {
                gap: 14px;
            }

            .elive-hero,
            .elive-panel {
                padding: 18px;
            }

            .elive-title {
                font-size: 23px;
            }

            .elive-detail {
                gap: 14px;
            }

            .elive-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-button {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .elive-hero,
            .elive-panel,
            .elive-stat {
                border-radius: 16px;
            }

            .elive-hero {
                display: grid;
                grid-template-columns: 1fr;
                gap: 14px;
                padding: 16px;
            }

            .elive-title {
                font-size: 21px;
            }

            .elive-badges {
                margin-top: 0;
            }

            .elive-stats {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .elive-stat {
                padding: 15px 16px;
            }

            .elive-stat-value {
                font-size: 26px;
            }

            .elive-panel {
                padding: 16px;
            }

            .elive-panel h2 {
                font-size: 17px;
            }

            .elive-panel-note {
                margin-bottom: 14px;
                font-size: 13px;
                line-height: 1.5;
            }

            .elive-search {
                display: block;
            }

            .elive-result {
                min-height: 78px;
                padding: 13px;
            }

            .elive-result-meta {
                line-height: 1.45;
            }

            .elive-detail {
                display: grid;
                grid-template-columns: 1fr;
                gap: 5px;
                padding-bottom: 10px;
            }

            .elive-detail span:last-child {
                text-align: left;
            }

            .elive-actions {
                grid-template-columns: 1fr;
            }

            .elive-table-wrap {
                display: none;
            }

            .elive-mobile-activity {
                display: grid;
                gap: 10px;
            }

            .elive-empty {
                padding: 24px 10px;
                font-size: 13px;
            }
        }

        @media (max-width: 390px) {
            .elive-checkin {
                gap: 12px;
            }

            .elive-hero,
            .elive-panel {
                padding: 14px;
            }

            .elive-title {
                font-size: 19px;
            }

            .elive-stat-value {
                font-size: 24px;
            }

            .elive-activity-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="elive-checkin">
        <section class="elive-hero">
            <div>
                <h1 class="elive-title">{{ $event->title }}</h1>
                <p class="elive-subtitle">
                    {{ $event->venue_name ?: 'Venue not specified' }}
                    @if ($event->event_date)
                        · {{ \Illuminate\Support\Carbon::parse($event->event_date)->format('d M Y') }}
                    @endif
                </p>
            </div>

            <div class="elive-badges">
                <span class="elive-badge">{{ str($event->status)->headline() }}</span>
                <span class="elive-badge">{{ $checkInRate }}% checked in</span>
            </div>
        </section>

        <section class="elive-stats">
            <article class="elive-stat">
                <div class="elive-stat-label">Invitees</div>
                <div class="elive-stat-value">{{ number_format($totalInvitees) }}</div>
            </article>

            <article class="elive-stat">
                <div class="elive-stat-label">Expected Guests</div>
                <div class="elive-stat-value">{{ number_format($totalAllowedGuests) }}</div>
            </article>

            <article class="elive-stat">
                <div class="elive-stat-label">Guests Admitted</div>
                <div class="elive-stat-value">{{ number_format($totalGuestsAdmitted) }}</div>
            </article>

            <article class="elive-stat">
                <div class="elive-stat-label">Remaining Guests</div>
                <div class="elive-stat-value">{{ number_format($remainingGuests) }}</div>
            </article>
        </section>

        <section class="elive-grid">
            <article class="elive-panel">
                <h2>Manual Guest Search</h2>
                <p class="elive-panel-note">
                    Search this event by invitee name, phone number, or serial number.
                </p>

                <div class="elive-search">
                    <input
                        wire:model.live.debounce.350ms="search"
                        class="elive-input"
                        type="search"
                        placeholder="Search name, phone, or serial number"
                        autocomplete="off"
                    >
                </div>

                @if (filled(trim($search)))
                    @forelse ($searchResults as $invitee)
                        <button
                            type="button"
                            wire:click="selectInvitee({{ $invitee->id }})"
                            class="elive-result"
                        >
                            <div class="elive-result-name">{{ $invitee->name }}</div>
                            <div class="elive-result-meta">
                                {{ $invitee->phone ?: 'No phone' }}
                                · {{ $invitee->serial_number ?: 'No serial number' }}
                            </div>

                            <div class="elive-badges">
                                <span class="elive-badge">
                                    {{ $invitee->cardType?->name ?: 'No card type' }}
                                </span>
                                <span class="elive-badge">
                                    {{ (int) $invitee->checked_in_count }} checked in
                                </span>
                                <span class="elive-badge">
                                    {{ str($invitee->card_status ?: 'active')->headline() }}
                                </span>
                            </div>
                        </button>
                    @empty
                        <div class="elive-empty">
                            No invitee matched this search in the selected event.
                        </div>
                    @endforelse
                @else
                    <div class="elive-empty">
                        Enter a name, phone number, or serial number to begin.
                    </div>
                @endif
            </article>

            <article class="elive-panel">
                <h2>Invitee Verification</h2>
                <p class="elive-panel-note">
                    Confirm guest details and remaining allowance before admitting anyone.
                </p>

                @if ($selectedInvitee)
                    <div class="elive-guest-card">
                        <div class="elive-detail">
                            <span>Name</span>
                            <span>{{ $selectedInvitee->name }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Card Type</span>
                            <span>{{ $selectedInvitee->cardType?->name ?: 'Unassigned' }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Serial Number</span>
                            <span>{{ $selectedInvitee->serial_number ?: 'Not set' }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Table</span>
                            <span>{{ $selectedInvitee->table_number ?: 'Unassigned' }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Category</span>
                            <span>{{ $selectedInvitee->category ?: 'Uncategorized' }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>RSVP</span>
                            <span>{{ str($selectedInvitee->rsvp_status ?: 'pending')->headline() }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Card Status</span>
                            <span>{{ str($selectedInvitee->card_status ?: 'active')->headline() }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Guest Limit</span>
                            <span>{{ $selectedInvitee->gate_limit }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Already Checked In</span>
                            <span>{{ (int) $selectedInvitee->checked_in_count }}</span>
                        </div>

                        <div class="elive-detail">
                            <span>Remaining Allowance</span>
                            <span>{{ $selectedInvitee->remaining_guest_limit }}</span>
                        </div>

                        <div>
                            <label
                                for="guestsToCheckIn"
                                class="elive-stat-label"
                            >
                                Guests arriving now
                            </label>

                            <input
                                id="guestsToCheckIn"
                                wire:model="guestsToCheckIn"
                                class="elive-input"
                                type="number"
                                min="1"
                                max="{{ max(1, $selectedInvitee->remaining_guest_limit) }}"
                            >

                            @error('guestsToCheckIn')
                                <div style="margin-top:6px;color:#B91C1C;font-size:13px;">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="elive-actions">
                            <button
                                type="button"
                                wire:click="manualCheckIn"
                                wire:loading.attr="disabled"
                                class="elive-button elive-button-primary"
                                @disabled($selectedInvitee->remaining_guest_limit <= 0)
                            >
                                Complete Check-in
                            </button>

                            <button
                                type="button"
                                wire:click="clearSelection"
                                class="elive-button elive-button-secondary"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                @else
                    <div class="elive-empty">
                        Select an invitee from the search results.
                    </div>
                @endif
            </article>
        </section>

        <section class="elive-panel">
            <h2>Recent Check-ins</h2>
            <p class="elive-panel-note">
                Latest gate transactions for this event.
            </p>

            <div class="elive-table-wrap">
                <table class="elive-table">
                    <thead>
                        <tr>
                            <th>Invitee</th>
                            <th>Guests</th>
                            <th>Method</th>
                            <th>Officer</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($recentCheckIns as $checkIn)
                            <tr>
                                <td>{{ $checkIn->invitee?->name ?: 'Unknown invitee' }}</td>
                                <td>{{ number_format((int) $checkIn->guests_checked_in) }}</td>
                                <td>{{ str($checkIn->checkin_method ?: 'unknown')->headline() }}</td>
                                <td>{{ $checkIn->checkedInBy?->name ?: 'System' }}</td>
                                <td>{{ str($checkIn->status ?: 'unknown')->headline() }}</td>
                                <td>
                                    {{ $checkIn->checked_in_at
                                        ? \Illuminate\Support\Carbon::parse($checkIn->checked_in_at)->format('h:i:s A')
                                        : '—'
                                    }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="elive-empty">
                                    No check-in activity has been recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="elive-mobile-activity">
                @forelse ($recentCheckIns as $checkIn)
                    <article class="elive-activity-card">
                        <div class="elive-activity-head">
                            <div class="elive-activity-name">
                                {{ $checkIn->invitee?->name ?: 'Unknown invitee' }}
                            </div>

                            <div class="elive-activity-time">
                                {{ $checkIn->checked_in_at
                                    ? \Illuminate\Support\Carbon::parse($checkIn->checked_in_at)->format('h:i A')
                                    : '—'
                                }}
                            </div>
                        </div>

                        <div class="elive-activity-meta">
                            <div>
                                <small>Guests</small>
                                <strong>{{ number_format((int) $checkIn->guests_checked_in) }}</strong>
                            </div>

                            <div>
                                <small>Method</small>
                                <strong>{{ str($checkIn->checkin_method ?: 'unknown')->headline() }}</strong>
                            </div>

                            <div>
                                <small>Officer</small>
                                <strong>{{ $checkIn->checkedInBy?->name ?: 'System' }}</strong>
                            </div>

                            <div>
                                <small>Status</small>
                                <strong>{{ str($checkIn->status ?: 'unknown')->headline() }}</strong>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="elive-empty">
                        No check-in activity has been recorded yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
