<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Verification - eLive Card</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

@php
    $eventName = $invitee->event?->title ?? $invitee->event?->name ?? 'Event';

    $allowedGuestsValue = (int) ($allowedGuests ?? $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? $invitee->cardType?->allowed_guests ?? $invitee->cardType?->allowed_people ?? 1);
    $confirmedGuestsValue = (int) ($confirmedGuests ?? $invitee->confirmed_guests ?? 0);
    $gateLimitValue = (int) ($gateLimit ?? (
        $invitee->rsvp_status === 'attending' && $confirmedGuestsValue > 0
            ? min($confirmedGuestsValue, $allowedGuestsValue)
            : (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true) ? 0 : $allowedGuestsValue)
    ));

    $checkedInCountValue = (int) ($checkedInCount ?? $invitee->checked_in_count ?? 0);
    $remainingGuestsValue = max(0, (int) ($remainingGuests ?? ($gateLimitValue - $checkedInCountValue)));

    $rsvpStatus = $invitee->rsvp_status ?? 'pending';
    $cardStatus = $invitee->card_status ?? 'active';

    $rsvpLabel = str($rsvpStatus)->replace('_', ' ')->title();
    $cardLabel = str($cardStatus)->replace('_', ' ')->upper();

    $rsvpBadgeClass = match ($rsvpStatus) {
        'attending' => 'bg-green-100 text-green-800 border-green-200',
        'not_attending', 'declined' => 'bg-red-100 text-red-800 border-red-200',
        'maybe' => 'bg-orange-100 text-orange-800 border-orange-200',
        default => 'bg-orange-100 text-orange-800 border-orange-200',
    };

    $cardBadgeClass = match ($cardStatus) {
        'blocked', 'cancelled' => 'bg-red-100 text-red-800 border-red-200',
        'active', 'generated', 'sent' => 'bg-green-100 text-green-800 border-green-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };
@endphp

<body class="min-h-screen bg-[#F8FAFC] text-[#111827]">
    <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center justify-center px-4 py-6">
        <section class="w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl">
            {{-- Header --}}
            <div class="bg-[#213B73] px-5 py-5 text-white sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                            eLive Card Gate Verification
                        </p>

                        <h1 class="mt-2 truncate text-2xl font-black leading-tight sm:text-3xl">
                            {{ $invitee->name }}
                        </h1>

                        <p class="mt-1 truncate text-sm font-semibold text-white/75">
                            {{ $eventName }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="space-y-5 p-5 sm:p-6">
                {{-- Alerts --}}
                @if (session('success'))
                    <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- Invitee details --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-black uppercase text-slate-500">Phone</div>
                        <div class="mt-1 break-all text-sm font-black text-[#111827]">{{ $invitee->phone ?? '—' }}</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-black uppercase text-slate-500">Serial</div>
                        <div class="mt-1 break-all text-sm font-black text-[#111827]">{{ $invitee->serial_number ?? '—' }}</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-black uppercase text-slate-500">Card Type</div>
                        <div class="mt-1 text-sm font-black text-[#111827]">{{ $invitee->cardType?->name ?? '—' }}</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-black uppercase text-slate-500">Table</div>
                        <div class="mt-1 text-sm font-black text-[#111827]">{{ $invitee->table_number ?? '—' }}</div>
                    </div>
                </div>

                {{-- Status badges --}}
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-black uppercase text-slate-500">RSVP Status</div>
                        <div class="mt-2 inline-flex rounded-full border px-3 py-1 text-xs font-black {{ $rsvpBadgeClass }}">
                            {{ $rsvpLabel }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-black uppercase text-slate-500">Card Status</div>
                        <div class="mt-2 inline-flex rounded-full border px-3 py-1 text-xs font-black {{ $cardBadgeClass }}">
                            {{ $cardLabel }}
                        </div>
                    </div>
                </div>

                {{-- Guest limits --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-2xl bg-slate-100 p-4 text-center sm:col-span-1">
                        <div class="text-[11px] font-black uppercase text-slate-500">Allowed</div>
                        <div class="mt-1 text-3xl font-black text-[#111827]">{{ $allowedGuestsValue }}</div>
                    </div>

                    <div class="rounded-2xl bg-blue-50 p-4 text-center sm:col-span-1">
                        <div class="text-[11px] font-black uppercase text-slate-500">Confirmed</div>
                        <div class="mt-1 text-3xl font-black text-[#213B73]">{{ $confirmedGuestsValue }}</div>
                    </div>

                    <div class="rounded-2xl bg-orange-50 p-4 text-center sm:col-span-1">
                        <div class="text-[11px] font-black uppercase text-slate-500">Gate Limit</div>
                        <div class="mt-1 text-3xl font-black text-[#FD9618]">{{ $gateLimitValue }}</div>
                    </div>

                    <div class="rounded-2xl bg-green-50 p-4 text-center sm:col-span-1">
                        <div class="text-[11px] font-black uppercase text-slate-500">Checked In</div>
                        <div class="mt-1 text-3xl font-black text-green-700">{{ $checkedInCountValue }}</div>
                    </div>

                    <div class="col-span-2 rounded-2xl bg-[#213B73] p-4 text-center text-white sm:col-span-1">
                        <div class="text-[11px] font-black uppercase text-white/70">Remaining</div>
                        <div class="mt-1 text-3xl font-black">{{ $remainingGuestsValue }}</div>
                    </div>
                </div>

                @if ($rsvpStatus === 'attending' && $confirmedGuestsValue > 0)
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm font-bold text-[#213B73]">
                        Gate check-in is using the RSVP confirmed guest limit: {{ $confirmedGuestsValue }} guest(s).
                    </div>
                @elseif (in_array($rsvpStatus, ['not_attending', 'declined'], true))
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-800">
                        This invitee responded that they will not attend. Contact the event manager before allowing entry.
                    </div>
                @else
                    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4 text-sm font-bold text-orange-800">
                        RSVP is pending. Gate check-in is using the original allowed guest limit.
                    </div>
                @endif

                {{-- Check-in form --}}
                @auth
                    @if ($remainingGuestsValue > 0 && ! in_array($cardStatus, ['blocked', 'cancelled'], true) && ! in_array($rsvpStatus, ['not_attending', 'declined'], true))
                        <form method="POST" action="{{ route('gate.verify.check-in', $token) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label class="mb-2 block text-sm font-black text-[#111827]">
                                    Guests entering now
                                </label>

                                @if ($remainingGuestsValue <= 6)
                                    <div class="grid grid-cols-3 gap-3">
                                        @for ($i = 1; $i <= $remainingGuestsValue; $i++)
                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="guests_to_check_in"
                                                    value="{{ $i }}"
                                                    class="peer sr-only"
                                                    @checked($i === 1)
                                                    required
                                                >
                                                <span class="flex h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white text-sm font-black text-[#111827] transition peer-checked:border-[#213B73] peer-checked:bg-[#213B73] peer-checked:text-white">
                                                    {{ $i }}
                                                </span>
                                            </label>
                                        @endfor
                                    </div>
                                @else
                                    <select
                                        name="guests_to_check_in"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-base font-black text-[#111827] outline-none focus:border-[#213B73] focus:ring-4 focus:ring-[#213B73]/10"
                                        required
                                    >
                                        @for ($i = 1; $i <= $remainingGuestsValue; $i++)
                                            <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'Guest' : 'Guests' }}</option>
                                        @endfor
                                    </select>
                                @endif

                                @error('guests_to_check_in')
                                    <p class="mt-2 text-sm font-bold text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <button
                                type="submit"
                                class="w-full rounded-2xl bg-green-600 px-5 py-4 text-base font-black text-white shadow-sm transition hover:bg-green-700"
                            >
                                Confirm Check-in
                            </button>
                        </form>
                    @else
                        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-center text-sm font-black text-red-800">
                            No remaining guests, card blocked/cancelled, or RSVP is not attending.
                        </div>
                    @endif
                @else
                    <a
                        href="/admin/login"
                        class="block rounded-2xl bg-[#FD9618] px-5 py-4 text-center text-base font-black text-white shadow-sm transition hover:bg-orange-600"
                    >
                        Login to Check In
                    </a>
                @endauth
            </div>
        </section>
    </main>
</body>
</html>
