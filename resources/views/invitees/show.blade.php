<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invitee->event?->title ?? $invitee->event?->name ?? 'Invitation' }} | eLive Card</title>

    <link rel="icon" type="image/png" href="{{ asset('images/elive-card-favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/elive-card-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/elive-card-favicon.png') }}">
    <meta name="theme-color" content="#213B73">

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        eliveBlue: '#213B73',
                        eliveOrange: '#FD9618',
                        eliveDark: '#111827',
                        eliveSoft: '#F8FAFC',
                    }
                }
            }
        }
    </script>
</head>

<body class="min-h-screen bg-eliveSoft text-eliveDark">
    @php
        $event = $invitee->event;

        $eventName = $event?->title ?? $event?->name ?? 'Special Event';
        $eventDate = $event?->event_date ?? $event?->date ?? null;
        $eventTime = $event?->event_time ?? $event?->time ?? null;
        $eventVenue = $event?->venue ?? 'Venue to be announced';

        $cardType = $invitee->cardType?->name ?? 'Invitation';
        $guestCount = $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1;

        $rsvpStatus = $invitee->rsvp_status ?? 'pending';
        $statusText = strtoupper(str_replace('_', ' ', $rsvpStatus));

        $statusClass = match ($rsvpStatus) {
            'attending' => 'bg-green-50 text-green-700 border-green-200',
            'not_attending' => 'bg-red-50 text-red-700 border-red-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };

        $formattedDate = $eventDate
            ? \Carbon\Carbon::parse($eventDate)->format('d M Y')
            : 'To be announced';

        $formattedTime = $eventTime
            ? \Carbon\Carbon::parse($eventTime)->format('h:i A')
            : 'To be announced';
    @endphp

    <main class="min-h-screen">
        {{-- Simple Header --}}
    <header class="fixed left-0 right-0 top-0 z-50 bg-white/95 border-b border-slate-200 backdrop-blur">
    <div class="mx-auto flex max-w-md items-center justify-between px-4 py-4">
        <img
            src="{{ asset('images/elive-card-logo.png') }}"
            alt="eLive Card"
            class="h-11 w-auto object-contain"
        >

        <span class="rounded-full bg-eliveOrange px-4 py-2 text-xs font-black uppercase tracking-wide text-white">
            RSVP
        </span>
    </div>
</header>

        <section class="px-4 py-6">
            <div class="mx-auto max-w-md">
                {{-- Invitation Card --}}
                <div class="overflow-hidden rounded-3xl bg-white shadow-xl border border-slate-200">
                    {{-- Card Hero --}}
                    <div class="bg-eliveBlue px-6 py-7 text-center text-white">
                        <p class="text-xs font-bold uppercase tracking-widest text-eliveOrange">
                            You are invited
                        </p>

                        <h1 class="mt-3 text-2xl font-black leading-tight">
                            {{ $eventName }}
                        </h1>

                        <p class="mt-3 text-sm text-white/80">
                            Hello <span class="font-bold text-white">{{ $invitee->name }}</span>
                        </p>
                    </div>

                    <div class="space-y-4 p-5">
                        {{-- Invitee --}}
                        <div class="text-center">
                            <p class="text-sm text-slate-500">Invitation for</p>

                            <h2 class="text-2xl font-black text-eliveBlue">
                                {{ $invitee->name }}
                            </h2>

                            <span class="mt-2 inline-flex rounded-full border px-4 py-2 text-xs font-bold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        {{-- Event Details --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase text-slate-500">Date</p>
                                <p class="mt-1 font-bold">{{ $formattedDate }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase text-slate-500">Time</p>
                                <p class="mt-1 font-bold">{{ $formattedTime }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-bold uppercase text-slate-500">Venue</p>
                            <p class="mt-1 font-bold">{{ $eventVenue }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase text-slate-500">Card</p>
                                <p class="mt-1 font-bold text-eliveBlue">{{ $cardType }}</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase text-slate-500">Guests</p>
                                <p class="mt-1 font-bold text-eliveBlue">{{ $guestCount }}</p>
                            </div>
                        </div>

                        @if ($invitee->table_number)
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase text-slate-500">Table</p>
                                <p class="mt-1 font-bold">{{ $invitee->table_number }}</p>
                            </div>
                        @endif

                        {{-- QR --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center">
                            <p class="mb-3 text-sm font-black text-eliveBlue">
                                Gate Access
                            </p>

                            @if ($invitee->qr_code_url)
                                <img
                                    src="{{ $invitee->qr_code_url }}"
                                    alt="QR Code"
                                    class="mx-auto h-44 w-44 object-contain"
                                >
                            @else
                                <div class="mx-auto flex h-44 w-44 items-center justify-center rounded-2xl bg-slate-50 text-sm font-bold text-slate-400">
                                    QR not available
                                </div>
                            @endif

                            <p class="mt-3 text-xs font-bold uppercase text-slate-500">
                                Serial Number
                            </p>

                            <p class="mt-1 break-all font-black text-eliveBlue">
                                {{ $invitee->serial_number }}
                            </p>
                        </div>

                        {{-- Alerts --}}
                        @if (session('success'))
                            <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-700">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        {{-- RSVP --}}
                        <form method="POST" action="{{ route('invitee.rsvp', $invitee->short_code) }}" class="space-y-3">
                            @csrf

                            <button
                                type="submit"
                                name="status"
                                value="attending"
                                class="w-full rounded-2xl bg-eliveBlue px-5 py-4 font-black text-white shadow-md transition hover:opacity-95"
                            >
                                I will attend
                            </button>

                            <button
                                type="submit"
                                name="status"
                                value="not_attending"
                                class="w-full rounded-2xl border border-eliveOrange bg-white px-5 py-4 font-black text-eliveOrange transition hover:bg-orange-50"
                            >
                                I will not attend
                            </button>
                        </form>

                        <p class="text-center text-xs text-slate-500">
                            Show this QR code or serial number at the gate.
                        </p>
                    </div>
                </div>

                {{-- Footer --}}
                <footer class="py-5 text-center">
                    <p class="text-sm font-bold text-eliveBlue">
                        Powered by eLive <span class="text-eliveOrange">Card</span>
                    </p>
                </footer>
            </div>
        </section>
    </main>
</body>
</html>