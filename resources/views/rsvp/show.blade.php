<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RSVP Confirmation - {{ $invitee->event->name ?? 'eLive Card' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-[#F8FAFC] text-[#111827]">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-xl">
            <div class="bg-[#213B73] px-6 py-7 text-white">
                <p class="text-sm font-semibold uppercase tracking-wide text-white/75">
                    eLive Card RSVP
                </p>

                <h1 class="mt-2 text-2xl font-bold">
                    {{ $invitee->event->name ?? 'Event Invitation' }}
                </h1>

                <p class="mt-2 text-sm text-white/80">
                    Please confirm your attendance.
                </p>
            </div>

            <div class="space-y-6 px-6 py-6">
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <p class="text-sm text-gray-500">Dear</p>
                    <h2 class="text-2xl font-bold text-[#111827]">
                        {{ $invitee->name }}
                    </h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Card Type</p>
                        <p class="mt-1 font-bold">
                            {{ $invitee->cardType->name ?? 'N/A' }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Allowed Guests</p>
                        <p class="mt-1 font-bold">
                            {{ $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1 }}
                        </p>
                    </div>

                    @if (! empty($invitee->serial_number))
                        <div class="rounded-2xl bg-[#F8FAFC] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Serial Number</p>
                            <p class="mt-1 font-bold">
                                {{ $invitee->serial_number }}
                            </p>
                        </div>
                    @endif

                    @if (! empty($invitee->table_number))
                        <div class="rounded-2xl bg-[#F8FAFC] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Table Number</p>
                            <p class="mt-1 font-bold">
                                {{ $invitee->table_number }}
                            </p>
                        </div>
                    @endif
                </div>

                @if ($invitee->rsvp_status !== \App\Models\Invitee::RSVP_PENDING)
                    <div class="rounded-2xl border border-[#FD9618]/30 bg-[#FD9618]/10 p-4">
                        <p class="text-sm text-gray-600">Your current RSVP response is:</p>

                        <p class="mt-1 text-lg font-bold text-[#213B73]">
                            {{ \App\Models\Invitee::rsvpStatuses()[$invitee->rsvp_status] ?? ucfirst($invitee->rsvp_status) }}
                        </p>

                        @if ($invitee->rsvp_status === \App\Models\Invitee::RSVP_ATTENDING)
                            <p class="mt-1 text-sm text-gray-600">
                                Confirmed guests: {{ $invitee->confirmed_guests ?? 1 }}
                            </p>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('rsvp.submit', $invitee->rsvp_token) }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="mb-2 block text-sm font-bold">
                            Will you attend?
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 p-4 hover:border-[#213B73]">
                                <input
                                    type="radio"
                                    name="rsvp_status"
                                    value="attending"
                                    class="mr-3"
                                    {{ old('rsvp_status', $invitee->rsvp_status) === 'attending' ? 'checked' : '' }}
                                >

                                <span class="font-semibold">
                                    I will attend
                                </span>
                            </label>

                            <label class="flex cursor-pointer items-center rounded-2xl border border-gray-200 p-4 hover:border-[#213B73]">
                                <input
                                    type="radio"
                                    name="rsvp_status"
                                    value="not_attending"
                                    class="mr-3"
                                    {{ old('rsvp_status', $invitee->rsvp_status) === 'not_attending' ? 'checked' : '' }}
                                >

                                <span class="font-semibold">
                                    I will not attend
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="confirmed_guests" class="mb-2 block text-sm font-bold">
                            Number of guests attending
                        </label>

                        <select
                            id="confirmed_guests"
                            name="confirmed_guests"
                            class="w-full rounded-2xl border border-gray-300 px-4 py-3 focus:border-[#213B73] focus:ring-[#213B73]"
                        >
                            @for ($i = 1; $i <= max(1, (int) ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1)); $i++)
                                <option value="{{ $i }}" {{ (int) old('confirmed_guests', $invitee->confirmed_guests ?: 1) === $i ? 'selected' : '' }}>
                                    {{ $i }}
                                </option>
                            @endfor
                        </select>

                        <p class="mt-2 text-xs text-gray-500">
                            Maximum allowed guests: {{ $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1 }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-[#FD9618] px-5 py-3 font-bold text-white shadow hover:bg-[#e88913]"
                    >
                        Submit RSVP
                    </button>
                </form>

                @if (! empty($invitee->private_invitation_url))
                    <div class="border-t pt-4 text-center">
                        <a href="{{ $invitee->private_invitation_url }}" class="text-sm font-semibold text-[#213B73] hover:underline">
                            Back to invitation page
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </main>
</body>
</html>