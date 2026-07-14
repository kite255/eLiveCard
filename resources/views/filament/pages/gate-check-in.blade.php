<x-filament-panels::page>
    <style>
        .elive-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
        }

        .elive-btn {
            min-height: 44px;
            border-radius: 12px;
            font-weight: 900;
            transition: 0.18s ease;
        }

        .elive-btn:hover {
            transform: translateY(-1px);
        }
    </style>

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="rounded-3xl bg-[#213B73] p-6 text-white shadow-xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.25em] text-white/60">
                        eLive Card
                    </p>

                    <h1 class="mt-2 text-3xl font-black tracking-tight">
                        Gate Check-in
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm font-semibold text-white/75">
                        Select an event to open the professional QR scanner, manual search, and recent check-in page.
                    </p>
                </div>

                <div class="rounded-full bg-white/10 px-5 py-2 text-sm font-black ring-1 ring-white/10">
                    Scanner Access
                </div>
            </div>
        </div>

        @if ($events->isEmpty())
            <div class="elive-card rounded-3xl p-8 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-[#213B73]">
                    <x-heroicon-o-qr-code class="h-8 w-8" />
                </div>

                <h2 class="mt-4 text-2xl font-black text-slate-900">
                    No assigned events
                </h2>

                <p class="mx-auto mt-2 max-w-xl text-sm font-semibold leading-6 text-slate-500">
                    You are not assigned to any event for gate check-in. Ask the Super Admin or Event Admin to assign you under:
                </p>

                <p class="mt-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-black text-[#213B73]">
                    Events → Open Event → Assigned Users
                </p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($events as $event)
                    <div class="elive-card rounded-3xl p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-black text-[#213B73]">
                                    {{ $event->title ?? $event->name ?? 'Untitled Event' }}
                                </h2>

                                <p class="mt-2 text-sm font-semibold text-slate-500">
                                    {{ $event->event_date?->format('d M Y') ?? 'Date not set' }}
                                </p>

                                <p class="mt-1 text-sm font-semibold text-slate-500">
                                    {{ $event->venue_name ?? $event->venue_address ?? 'Venue not set' }}
                                </p>
                            </div>

                            <span class="rounded-full bg-[#FD9618]/15 px-3 py-1 text-xs font-black text-[#C2410C]">
                                {{ $event->status_display ?? ucfirst((string) $event->status) }}
                            </span>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2">
                            <div class="rounded-2xl bg-slate-50 p-3 text-center">
                                <p class="text-xs font-bold text-slate-500">
                                    Invitees
                                </p>
                                <p class="text-xl font-black text-[#213B73]">
                                    {{ $event->invitees_count ?? $event->invitees()->count() }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-3 text-center">
                                <p class="text-xs font-bold text-slate-500">
                                    Checked
                                </p>
                                <p class="text-xl font-black text-green-600">
                                    {{ $event->checked_in_invitees_count ?? 0 }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-3 text-center">
                                <p class="text-xs font-bold text-slate-500">
                                    RSVP
                                </p>
                                <p class="text-xl font-black text-[#213B73]">
                                    {{ $event->rsvp_attending_count ?? 0 }}
                                </p>
                            </div>
                        </div>

                        <a
                            href="{{ route('gate.check-in.show', $event) }}"
                            class="elive-btn mt-5 inline-flex w-full items-center justify-center gap-2 bg-[#213B73] px-5 py-3 text-white shadow"
                        >
                            <x-heroicon-o-qr-code class="h-5 w-5" />
                            Open Scanner
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
