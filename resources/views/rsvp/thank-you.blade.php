<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank You - RSVP Confirmed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-[#F8FAFC] text-[#111827]">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-xl">
            <div class="bg-[#213B73] px-6 py-8 text-center text-white">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/15 text-3xl font-bold">
                    ✓
                </div>

                <h1 class="text-2xl font-bold">
                    Thank You
                </h1>

                <p class="mt-2 text-sm text-white/80">
                    Your RSVP response has been recorded successfully.
                </p>
            </div>

            <div class="space-y-4 px-6 py-6">
                <div class="rounded-2xl bg-[#F8FAFC] p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invitee</p>
                    <p class="mt-1 font-bold">{{ $invitee->name }}</p>
                </div>

                <div class="rounded-2xl bg-[#F8FAFC] p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Event</p>
                    <p class="mt-1 font-bold">{{ $invitee->event->name ?? 'Event' }}</p>
                </div>

                <div class="rounded-2xl bg-[#F8FAFC] p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">RSVP Status</p>
                    <p class="mt-1 font-bold text-[#213B73]">
                        {{ \App\Models\Invitee::rsvpStatuses()[$invitee->rsvp_status] ?? ucfirst($invitee->rsvp_status) }}
                    </p>
                </div>

                @if ($invitee->rsvp_status === \App\Models\Invitee::RSVP_ATTENDING)
                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Confirmed Guests</p>
                        <p class="mt-1 font-bold">{{ $invitee->confirmed_guests ?? 1 }}</p>
                    </div>
                @endif

                @if (! empty($invitee->serial_number))
                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Serial Number</p>
                        <p class="mt-1 font-bold">{{ $invitee->serial_number }}</p>
                    </div>
                @endif

                <p class="pt-2 text-center text-sm text-gray-500">
                    Please keep your invitation card safe for event check-in.
                </p>

                @if (! empty($invitee->private_invitation_url))
                    <a
                        href="{{ $invitee->private_invitation_url }}"
                        class="block rounded-2xl bg-[#213B73] px-5 py-3 text-center font-bold text-white hover:bg-[#1b315f]"
                    >
                        View Invitation Page
                    </a>
                @endif
            </div>
        </div>
    </main>
</body>
</html>