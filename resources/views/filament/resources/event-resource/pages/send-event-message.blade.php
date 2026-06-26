<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="inline-flex items-center rounded-full bg-[#213B73]/10 px-3 py-1 text-xs font-semibold text-[#213B73]">
                        Event Communication
                    </div>

                    <h2 class="mt-3 text-2xl font-bold text-[#111827]">
                        Message Center
                    </h2>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                        Send SMS invitations, prepare WhatsApp communication, generate missing cards, and monitor delivery for this event.
                    </p>
                </div>

                <div class="w-full rounded-xl bg-[#F8FAFC] px-4 py-4 text-sm ring-1 ring-gray-200 md:w-auto md:min-w-72">
                    <div class="font-bold text-[#213B73]">
                        {{ $this->eventName }}
                    </div>

                    <div class="mt-2 text-gray-600">
                        <span class="font-semibold text-[#111827]">Date:</span>
                        {{ $this->eventDate }}
                    </div>

                    <div class="mt-1 text-gray-600">
                        <span class="font-semibold text-[#111827]">Venue:</span>
                        {{ $this->eventVenue }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Statistics --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-500">
                        Total Invitees
                    </div>

                    <div class="rounded-lg bg-[#213B73]/10 px-2 py-1 text-xs font-bold text-[#213B73]">
                        Event
                    </div>
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ number_format($this->inviteesCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    All invitees added to this event.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-500">
                        Generated Cards
                    </div>

                    <div class="rounded-lg bg-green-50 px-2 py-1 text-xs font-bold text-green-700">
                        Ready
                    </div>
                </div>

                <div class="mt-3 text-3xl font-bold text-green-700">
                    {{ number_format($this->generatedCardsCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Invitees with generated invitation cards.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-500">
                        Missing Cards
                    </div>

                    <div class="rounded-lg bg-[#FD9618]/10 px-2 py-1 text-xs font-bold text-[#FD9618]">
                        Action
                    </div>
                </div>

                <div class="mt-3 text-3xl font-bold text-[#FD9618]">
                    {{ number_format($this->missingCardsCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Generate these before sending invitations.
                </p>
            </div>
        </div>

        {{-- SMS Readiness --}}
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Eligible for SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ number_format($this->eligibleSmsInviteesCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Has phone, private link, and generated card.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Unsent Eligible
                </div>

                <div class="mt-3 text-3xl font-bold text-[#FD9618]">
                    {{ number_format($this->unsentEligibleSmsInviteesCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Ready and not yet queued or sent.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Queued SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ number_format($this->queuedSmsCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Waiting for queue worker.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Sent SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-green-700">
                    {{ number_format($this->sentSmsCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Successfully sent SMS logs.
                </p>
            </div>
        </div>

        {{-- RSVP / Delivery --}}
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Failed SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-red-600">
                    {{ number_format($this->failedSmsCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Requires review or retry.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Confirmed / Attending
                </div>

                <div class="mt-3 text-3xl font-bold text-green-700">
                    {{ number_format($this->attendingCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Invitees who confirmed attendance.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Pending RSVP
                </div>

                <div class="mt-3 text-3xl font-bold text-[#FD9618]">
                    {{ number_format($this->pendingRsvpCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Invitees who have not responded.
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    WhatsApp / Messages
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ number_format($this->sentMessagesCount) }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Sent, accepted, delivered, or read.
                </p>
            </div>
        </div>

        {{-- Message Center --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#111827]">
                        Message Center Actions
                    </h3>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                        Use these actions to prepare invitation cards and send event messages. For now, SMS sending is active. WhatsApp will be added into this same center.
                    </p>
                </div>

                <div class="rounded-full bg-[#F8FAFC] px-4 py-2 text-xs font-semibold text-gray-600 ring-1 ring-gray-200">
                    SMS active · WhatsApp next
                </div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <a
                    href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $this->record]) }}"
                    class="rounded-xl bg-[#213B73] px-4 py-4 text-center text-sm font-bold text-white shadow-sm transition hover:bg-[#1D356A]"
                >
                    Open Invitees
                </a>

                <button
                    type="button"
                    wire:click="generateMissingCards"
                    @disabled($this->missingCardsCount === 0)
                    class="rounded-xl px-4 py-4 text-center text-sm font-bold shadow-sm ring-1 transition
                        {{ $this->missingCardsCount === 0
                            ? 'cursor-not-allowed bg-gray-100 text-gray-400 ring-gray-200'
                            : 'bg-[#FD9618] text-white ring-[#FD9618] hover:opacity-90' }}"
                >
                    Generate Missing Cards
                </button>

                <button
                    type="button"
                    wire:click="sendSmsInvitations"
                    @disabled($this->unsentEligibleSmsInviteesCount === 0)
                    class="rounded-xl px-4 py-4 text-center text-sm font-bold shadow-sm ring-1 transition
                        {{ $this->unsentEligibleSmsInviteesCount === 0
                            ? 'cursor-not-allowed bg-gray-100 text-gray-400 ring-gray-200'
                            : 'bg-green-600 text-white ring-green-600 hover:bg-green-700' }}"
                >
                    Send SMS Invitations
                </button>

                <div class="rounded-xl bg-[#F8FAFC] px-4 py-4 text-center text-sm font-bold text-gray-500 ring-1 ring-gray-200">
                    WhatsApp Coming Next
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-dashed border-gray-300 bg-[#F8FAFC] p-4 text-sm leading-6 text-gray-600">
                    <strong class="text-[#111827]">Live flow:</strong>
                    generate missing cards first, then send SMS invitations. SMS will only be queued for invitees with phone number, private link, and generated card.
                </div>

                <div class="rounded-xl border border-dashed border-gray-300 bg-[#F8FAFC] p-4 text-sm leading-6 text-gray-600">
                    <strong class="text-[#111827]">Duplicate protection:</strong>
                    invitees with invitation SMS already queued, pending, sending, or sent are skipped automatically.
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>