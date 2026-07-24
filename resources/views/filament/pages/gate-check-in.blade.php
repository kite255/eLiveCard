<x-filament-panels::page>
    <style>
        .elive-gate-wrap {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-dark: #111827;
            --elive-bg: #F8FAFC;
            --elive-border: #E5E7EB;
            width: 100%;
            min-width: 0;
        }

        .elive-gate-wrap,
        .elive-gate-wrap *,
        .elive-gate-wrap *::before,
        .elive-gate-wrap *::after {
            box-sizing: border-box;
        }

        .elive-gate-wrap img,
        .elive-gate-wrap svg,
        .elive-gate-wrap video,
        .elive-gate-wrap canvas {
            max-width: 100%;
        }

        .elive-hero {
            overflow: hidden;
            position: relative;
            background: var(--elive-blue);
            color: #FFFFFF;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 18px 45px rgba(17, 24, 39, 0.16);
        }

        .elive-hero::before {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -80px;
            top: -110px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
        }

        .elive-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .elive-brand-label {
            margin: 0;
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.24em;
            text-transform: uppercase;
        }

        .elive-title {
            margin: 8px 0 0;
            color: #FFFFFF;
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 900;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .elive-subtitle {
            max-width: 680px;
            margin: 10px 0 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 14px;
            font-weight: 650;
            line-height: 1.7;
        }

        .elive-access-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            min-height: 38px;
            padding: 8px 14px;
            border-radius: 999px;
            color: #FFFFFF;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .elive-events-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .elive-card {
            min-width: 0;
            overflow: hidden;
            background: #FFFFFF;
            border: 1px solid var(--elive-border);
            border-radius: 22px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
        }

        .elive-card-body {
            padding: 20px;
        }

        .elive-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .elive-event-copy {
            min-width: 0;
        }

        .elive-event-title {
            margin: 0;
            color: var(--elive-blue);
            font-size: 20px;
            font-weight: 900;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .elive-event-date {
            margin: 14px 0 0;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        .elive-event-venue {
            margin: 5px 0 0;
            color: #64748B;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .elive-status {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(253, 150, 24, 0.14);
            color: #9A4B00;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }

        .elive-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px;
            margin-top: 18px;
        }

        .elive-stat {
            min-width: 0;
            background: #F8FAFC;
            border-radius: 14px;
            padding: 13px 8px;
            text-align: center;
        }

        .elive-stat-label {
            margin: 0;
            color: #64748B;
            font-size: 10px;
            font-weight: 850;
            line-height: 1.3;
        }

        .elive-stat-value {
            margin: 5px 0 0;
            color: var(--elive-blue);
            font-size: 23px;
            font-weight: 950;
            line-height: 1;
        }

        .elive-stat-value-success {
            color: #16A34A;
        }

        .elive-btn {
            display: inline-flex;
            width: 100%;
            min-height: 50px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
            border-radius: 14px;
            background: var(--elive-blue);
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 12px 24px rgba(33, 59, 115, 0.18);
            transition: transform 0.18s ease, background 0.18s ease;
            touch-action: manipulation;
        }

        .elive-btn:hover {
            transform: translateY(-1px);
            background: #1B3160;
            color: #FFFFFF;
        }

        .elive-empty {
            background: #FFFFFF;
            border: 1px solid var(--elive-border);
            border-radius: 24px;
            padding: 40px 24px;
            text-align: center;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
        }

        .elive-empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: #F1F5F9;
            color: var(--elive-blue);
        }

        .elive-empty-title {
            margin: 18px 0 0;
            color: var(--elive-dark);
            font-size: 24px;
            font-weight: 900;
        }

        .elive-empty-text {
            max-width: 560px;
            margin: 10px auto 0;
            color: #64748B;
            font-size: 14px;
            font-weight: 650;
            line-height: 1.6;
        }

        .elive-empty-help {
            max-width: 560px;
            margin: 16px auto 0;
            padding: 12px 14px;
            border-radius: 14px;
            background: #F8FAFC;
            color: var(--elive-blue);
            font-size: 13px;
            font-weight: 850;
            overflow-wrap: anywhere;
        }

        @media (min-width: 768px) {
            .elive-hero-content {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        @media (max-width: 1200px) {
            .elive-events-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .elive-gate-wrap {
                max-width: 100%;
            }

            .elive-hero {
                padding: 22px;
                border-radius: 20px;
            }

            .elive-events-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .elive-card {
                border-radius: 18px;
            }

            .elive-card-body {
                padding: 18px;
            }
        }

        @media (max-width: 520px) {
            .elive-hero {
                padding: 18px;
            }

            .elive-title {
                font-size: 26px;
            }

            .elive-card-head {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .elive-status {
                width: fit-content;
            }

            .elive-stats {
                grid-template-columns: 1fr;
            }

            .elive-stat {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 13px 14px;
                text-align: left;
            }

            .elive-stat-value {
                margin-top: 0;
                font-size: 22px;
            }

            .elive-empty {
                padding: 30px 18px;
            }
        }

        @media (max-width: 390px) {
            .elive-hero {
                padding: 16px;
                border-radius: 18px;
            }

            .elive-title {
                font-size: 23px;
            }

            .elive-card-body {
                padding: 15px;
            }

            .elive-event-title {
                font-size: 18px;
            }
        }

        .dark .elive-card,
        .dark .elive-empty {
            background: #111827;
            border-color: #374151;
        }

        .dark .elive-event-title,
        .dark .elive-empty-title {
            color: #FFFFFF;
        }

        .dark .elive-event-date {
            color: #E5E7EB;
        }

        .dark .elive-event-venue,
        .dark .elive-empty-text {
            color: #94A3B8;
        }

        .dark .elive-stat,
        .dark .elive-empty-help {
            background: #1F2937;
        }
    </style>

    <div class="elive-gate-wrap mx-auto max-w-6xl space-y-6">
        <section class="elive-hero">
            <div class="elive-hero-content">
                <div>
                    <p class="elive-brand-label">
                        eLive Card
                    </p>

                    <h1 class="elive-title">
                        Gate Check-in
                    </h1>

                    <p class="elive-subtitle">
                        Select an assigned event to open the QR scanner,
                        manual guest search, and recent check-in activity.
                    </p>
                </div>

                <div class="elive-access-badge">
                    Scanner Access
                </div>
            </div>
        </section>

        @if ($events->isEmpty())
            <section class="elive-empty">
                <div class="elive-empty-icon">
                    <x-heroicon-o-qr-code class="h-8 w-8" />
                </div>

                <h2 class="elive-empty-title">
                    No assigned events
                </h2>

                <p class="elive-empty-text">
                    You are not currently assigned to any event for gate
                    check-in. Contact the Event Manager for assignment.
                </p>

                <p class="elive-empty-help">
                    Events → Open Event → Assigned Users
                </p>
            </section>
        @else
            <section class="elive-events-grid">
                @foreach ($events as $event)
                    <article class="elive-card">
                        <div class="elive-card-body">
                            <div class="elive-card-head">
                                <div class="elive-event-copy">
                                    <h2 class="elive-event-title">
                                        {{ $event->title
                                            ?? $event->name
                                            ?? 'Untitled Event'
                                        }}
                                    </h2>

                                    <p class="elive-event-date">
                                        {{ $event->event_date?->format('d M Y')
                                            ?? 'Date not set'
                                        }}
                                    </p>

                                    <p class="elive-event-venue">
                                        {{ $event->venue_name
                                            ?? $event->venue_address
                                            ?? 'Venue not set'
                                        }}
                                    </p>
                                </div>

                                <span class="elive-status">
                                    {{ $event->status_display
                                        ?? ucfirst((string) $event->status)
                                    }}
                                </span>
                            </div>

                            <div class="elive-stats">
                                <div class="elive-stat">
                                    <p class="elive-stat-label">
                                        Expected
                                    </p>

                                    <p class="elive-stat-value">
                                        {{ (int) (
                                            $event->total_allowed_guests
                                            ?? $event->invitees()
                                                ->sum('allowed_guests')
                                        ) }}
                                    </p>
                                </div>

                                <div class="elive-stat">
                                    <p class="elive-stat-label">
                                        Checked In
                                    </p>

                                    <p class="elive-stat-value elive-stat-value-success">
                                        {{ (int) (
                                            $event->total_checked_in_guests
                                            ?? $event->invitees()
                                                ->sum('checked_in_count')
                                        ) }}
                                    </p>
                                </div>

                                <div class="elive-stat">
                                    <p class="elive-stat-label">
                                        Remaining
                                    </p>

                                    <p class="elive-stat-value">
                                        {{
                                            max(
                                                (int) (
                                                    $event->total_allowed_guests
                                                    ?? $event->invitees()
                                                        ->sum('allowed_guests')
                                                )
                                                -
                                                (int) (
                                                    $event->total_checked_in_guests
                                                    ?? $event->invitees()
                                                        ->sum('checked_in_count')
                                                ),
                                                0
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <a
                                href="{{ route('gate.check-in.show', $event) }}"
                                class="elive-btn"
                            >
                                <x-heroicon-o-qr-code class="h-5 w-5" />
                                Open Scanner
                            </a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</x-filament-panels::page>
