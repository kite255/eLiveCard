<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        RSVP Confirmation - {{ $invitee->event->title ?? $invitee->event->name ?? 'eLive Card' }}
    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

@php
    $eventTitle = $invitee->event->title ?? $invitee->event->name ?? 'Event Invitation';

    $maxGuests = max(1, (int) (
        $invitee->final_allowed_guests
        ?? $invitee->allowed_guests
        ?? 1
    ));

    $currentRsvpStatus = old(
        'rsvp_status',
        $invitee->rsvp_status === \App\Models\Invitee::RSVP_PENDING
            ? 'attending'
            : $invitee->rsvp_status
    );

    $currentConfirmedGuests = (int) old(
        'confirmed_guests',
        $invitee->confirmed_guests ?: 1
    );

    $currentConfirmedGuests = min(max(1, $currentConfirmedGuests), $maxGuests);

    $rsvpLabel = \App\Models\Invitee::rsvpStatuses()[$invitee->rsvp_status]
        ?? ucfirst(str_replace('_', ' ', $invitee->rsvp_status));
@endphp

<body class="min-h-screen bg-[#F8FAFC] text-[#111827]">
    <main class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-xl border border-gray-100">

            {{-- Header --}}
            <div class="bg-[#213B73] px-6 py-7 text-white">
                <p class="text-sm font-semibold uppercase tracking-wide text-white/75">
                    eLive Card RSVP
                </p>

                <h1 class="mt-2 text-2xl font-bold leading-tight">
                    {{ $eventTitle }}
                </h1>

                <p class="mt-2 text-sm text-white/80">
                    Please confirm your attendance.
                </p>
            </div>

            <div class="space-y-6 px-6 py-6">

                {{-- Validation Error --}}
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- Invitee --}}
                <div>
                    <p class="text-sm text-gray-500">Dear</p>

                    <h2 class="text-2xl font-bold text-[#213B73]">
                        {{ $invitee->name }}
                    </h2>
                </div>

                {{-- Invitation Details --}}
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Card Type
                        </p>

                        <p class="mt-1 font-bold text-[#213B73]">
                            {{ $invitee->cardType->name ?? 'N/A' }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Allowed Guests
                        </p>

                        <p class="mt-1 font-bold text-[#213B73]">
                            {{ $maxGuests }}
                        </p>
                    </div>

                    @if (! empty($invitee->serial_number))
                        <div class="rounded-2xl bg-[#F8FAFC] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Serial Number
                            </p>

                            <p class="mt-1 font-bold text-[#213B73]">
                                {{ $invitee->serial_number }}
                            </p>
                        </div>
                    @endif

                    @if (! empty($invitee->table_number))
                        <div class="rounded-2xl bg-[#F8FAFC] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Table Number
                            </p>

                            <p class="mt-1 font-bold text-[#213B73]">
                                {{ $invitee->table_number }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Current RSVP --}}
                @if ($invitee->rsvp_status !== \App\Models\Invitee::RSVP_PENDING)
                    <div class="rounded-2xl border border-[#FD9618]/30 bg-[#FD9618]/10 p-4">
                        <p class="text-sm text-gray-600">
                            Your current RSVP response is:
                        </p>

                        <p class="mt-1 text-lg font-bold text-[#213B73]">
                            {{ $rsvpLabel }}
                        </p>

                        @if ($invitee->rsvp_status === \App\Models\Invitee::RSVP_ATTENDING)
                            <p class="mt-1 text-sm text-gray-600">
                                Confirmed guests: {{ $invitee->confirmed_guests ?? 1 }}
                            </p>
                        @endif
                    </div>
                @endif

                {{-- RSVP Form --}}
                <form method="POST" action="{{ route('rsvp.submit', $invitee->rsvp_token) }}" class="space-y-5">
                    @csrf

                    {{-- Attendance --}}
                    <div>
                        <label class="mb-2 block text-sm font-bold text-[#111827]">
                            Will you attend?
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 p-4 transition hover:border-[#213B73] hover:bg-[#F8FAFC]">
                                <input
                                    type="radio"
                                    name="rsvp_status"
                                    value="attending"
                                    class="mr-3 text-[#213B73] focus:ring-[#213B73]"
                                    {{ $currentRsvpStatus === 'attending' ? 'checked' : '' }}
                                >

                                <span class="font-semibold">
                                    I will attend
                                </span>
                            </label>

                            <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 p-4 transition hover:border-[#213B73] hover:bg-[#F8FAFC]">
                                <input
                                    type="radio"
                                    name="rsvp_status"
                                    value="not_attending"
                                    class="mr-3 text-[#213B73] focus:ring-[#213B73]"
                                    {{ $currentRsvpStatus === 'not_attending' ? 'checked' : '' }}
                                >

                                <span class="font-semibold">
                                    I will not attend
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- Guest Count --}}
                    <div id="guest-count-section">
                        <label for="confirmed_guests" class="mb-2 block text-sm font-bold text-[#111827]">
                            Number of guests attending
                        </label>

                        <select
                            id="confirmed_guests"
                            name="confirmed_guests"
                            class="w-full rounded-2xl border border-gray-300 px-4 py-3 focus:border-[#213B73] focus:ring-[#213B73]"
                        >
                            @for ($i = 1; $i <= $maxGuests; $i++)
                                <option value="{{ $i }}" {{ $currentConfirmedGuests === $i ? 'selected' : '' }}>
                                    {{ $i }}
                                </option>
                            @endfor
                        </select>

                        <p class="mt-2 text-xs text-gray-500">
                            Maximum allowed guests: {{ $maxGuests }}
                        </p>
                    </div>

                    {{-- Submit --}}
                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-[#FD9618] px-5 py-3 font-bold text-white shadow transition hover:bg-[#e88913] focus:outline-none focus:ring-2 focus:ring-[#FD9618] focus:ring-offset-2"
                    >
                        Submit RSVP
                    </button>
                </form>

                {{-- Back Link --}}
                <div class="border-t pt-4 text-center">
                    <a
                        href="{{ $invitee->private_invitation_url ?? route('invitee.page', $invitee->short_code) }}"
                        class="text-sm font-semibold text-[#213B73] hover:underline"
                    >
                        Back to invitation page
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        const rsvpRadios = document.querySelectorAll('input[name="rsvp_status"]');
        const guestSection = document.getElementById('guest-count-section');
        const guestSelect = document.getElementById('confirmed_guests');

        function toggleGuestCount() {
            const selected = document.querySelector('input[name="rsvp_status"]:checked')?.value;

            if (selected === 'not_attending') {
                guestSection.classList.add('hidden');
                guestSelect.value = 1;
            } else {
                guestSection.classList.remove('hidden');
            }
        }

        rsvpRadios.forEach((radio) => {
            radio.addEventListener('change', toggleGuestCount);
        });

        toggleGuestCount();
    </script>
</body>
</html>