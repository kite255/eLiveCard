<x-filament-panels::page>
    <style>
        .elive-gate-wrap {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-dark: #111827;
            --elive-bg: #F8FAFC;
            --elive-border: #E5E7EB;
        }

        .elive-hero {
            background: var(--elive-blue);
            color: #ffffff;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 18px 45px rgba(17, 24, 39, 0.16);
        }

        .elive-card {
            background: #ffffff;
            border: 1px solid var(--elive-border);
            border-radius: 22px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .elive-status {
            background: rgba(253, 150, 24, 0.14);
            color: #9a4b00;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 900;
        }

        .elive-stat {
            background: #F8FAFC;
            border-radius: 16px;
            padding: 14px 10px;
            text-align: center;
        }

        .elive-stat-label {
            color: #64748B;
            font-size: 12px;
            font-weight: 800;
        }

        .elive-stat-value {
            margin-top: 4px;
            color: var(--elive-blue);
            font-size: 24px;
            font-weight: 950;
            line-height: 1;
        }

        .elive-btn {
            display: inline-flex;
            width: 100%;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 14px;
            background: var(--elive-blue);
            color: #ffffff;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 12px 24px rgba(33, 59, 115, 0.18);
            transition: 0.18s ease;
        }

        .elive-btn:hover {
            transform: translateY(-1px);
            background: #1b3160;
            color: #ffffff;
        }

        .elive-empty {
            background: #ffffff;
            border: 1px solid var(--elive-border);
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
        }
    </style>

    <div class="elive-gate-wrap mx-auto max-w-6xl space-y-6">
        <div class="elive-hero">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.25em] text-white/70">
                        eLive Card
                    </p>

                    <h1 class="mt-2 text-3xl font-black tracking-tight text-white">
                        Gate Check-in
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-white/80">
                        Select an event to open the professional QR scanner, manual search, and recent check-in page.
                    </p>
                </div>

                <div class="rounded-full bg-white/15 px-5 py-2 text-sm font-black text-white ring-1 ring-white/20">
                    Scanner Access
                </div>
            </div>
        </div>

        @if ($events->isEmpty())
            <div class="elive-empty">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-[#213B73]">
                    <x-heroicon-o-qr-code class="h-8 w-8" />
                </div>

                <h2 class="mt-4 text-2xl font-black text-slate-900">
                    No assigned events
                </h2>

                <p class="mx-auto mt-2 max-w-xl text-sm font-semibold leading-6 text-slate-500">
                    You are not assigned to any event for gate check-in.
                </p>

                <p class="mx-auto mt-4 max-w-xl rounded-2xl bg-slate-50 px-4 py-3 text-sm font-black text-[#213B73]">
                    Events → Open Event → Assigned Users
                </p>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($events as $event)
                    <div class="elive-card">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="text-xl font-black leading-tight text-[#213B73]">
                                        {{ $event->title ?? $event->name ?? 'Untitled Event' }}
                                    </h2>

                                    <p class="mt-3 text-sm font-bold text-slate-700">
                                        {{ $event->event_date?->format('d M Y') ?? 'Date not set' }}
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-slate-500">
                                        {{ $event->venue_name ?? $event->venue_address ?? 'Venue not set' }}
                                    </p>
                                </div>

                                <span class="elive-status">
                                    {{ $event->status_display ?? ucfirst((string) $event->status) }}
                                </span>
                            </div>

                            <div class="mt-5 grid grid-cols-3 gap-2">
                                <div class="elive-stat">
                                    <p class="elive-stat-label">Invitees</p>
                                    <p class="elive-stat-value">
                                        {{ $event->invitees_count ?? $event->invitees()->count() }}
                                    </p>
                                </div>

                                <div class="elive-stat">
                                    <p class="elive-stat-label">Checked</p>
                                    <p class="elive-stat-value text-green-600">
                                        {{ $event->checked_in_invitees_count ?? 0 }}
                                    </p>
                                </div>

                                <div class="elive-stat">
                                    <p class="elive-stat-label">RSVP</p>
                                    <p class="elive-stat-value">
                                        {{ $event->rsvp_attending_count ?? 0 }}
                                    </p>
                                </div>
                            </div>

                            <a
                                href="{{ route('gate.check-in.show', $event) }}"
                                class="elive-btn mt-5"
                            >
                                <x-heroicon-o-qr-code class="h-5 w-5" />
                                Open Scanner
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>