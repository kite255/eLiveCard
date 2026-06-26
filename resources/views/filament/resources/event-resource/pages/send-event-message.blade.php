<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Hero Header --}}
        <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <div class="bg-[#213B73] px-6 py-6 text-white">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20">
                            Event Communication Hub
                        </div>

                        <h1 class="mt-4 text-2xl font-bold tracking-tight md:text-3xl">
                            Message Center
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-white/80">
                            Generate cards, send SMS and WhatsApp invitations, send reminders, and monitor delivery for this event.
                        </p>
                    </div>

                    <div class="rounded-2xl bg-white/10 p-4 text-sm ring-1 ring-white/20 lg:min-w-80">
                        <div class="text-xs font-semibold uppercase tracking-wide text-white/60">
                            Current Event
                        </div>

                        <div class="mt-2 text-base font-bold text-white">
                            {{ $this->eventName }}
                        </div>

                        <div class="mt-3 grid gap-2 text-white/80">
                            <div>
                                <span class="font-semibold text-white">Date:</span>
                                {{ $this->eventDate }}
                            </div>

                            <div>
                                <span class="font-semibold text-white">Venue:</span>
                                {{ $this->eventVenue }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Status Strip --}}
            <div class="grid gap-0 border-t border-gray-100 md:grid-cols-4">
                <div class="border-b border-gray-100 px-6 py-4 md:border-b-0 md:border-r">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Invitees</div>
                    <div class="mt-1 text-2xl font-bold text-[#213B73]">
                        {{ number_format($this->inviteesCount) }}
                    </div>
                </div>

                <div class="border-b border-gray-100 px-6 py-4 md:border-b-0 md:border-r">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cards Ready</div>
                    <div class="mt-1 text-2xl font-bold text-green-700">
                        {{ number_format($this->generatedCardsCount) }}
                    </div>
                </div>

                <div class="border-b border-gray-100 px-6 py-4 md:border-b-0 md:border-r">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ready to Send</div>
                    <div class="mt-1 text-2xl font-bold text-[#FD9618]">
                        {{ number_format($this->unsentEligibleSmsInviteesCount) }}
                    </div>
                </div>

                <div class="px-6 py-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Confirmed</div>
                    <div class="mt-1 text-2xl font-bold text-[#213B73]">
                        {{ number_format($this->attendingCount) }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Workflow --}}
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#213B73]/10 text-lg font-bold text-[#213B73]">
                        1
                    </div>

                    <div>
                        <h3 class="font-bold text-[#111827]">Prepare Cards</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600">
                            Generate invitation cards for invitees who are still missing cards.
                        </p>

                        <div class="mt-4 rounded-xl bg-[#F8FAFC] p-4">
                            <div class="text-xs font-semibold text-gray-500">Missing Cards</div>
                            <div class="mt-1 text-3xl font-bold text-[#FD9618]">
                                {{ number_format($this->missingCardsCount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#FD9618]/10 text-lg font-bold text-[#FD9618]">
                        2
                    </div>

                    <div>
                        <h3 class="font-bold text-[#111827]">Send Invitations</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600">
                            Send SMS or WhatsApp invitation links to eligible invitees.
                        </p>

                        <div class="mt-4 rounded-xl bg-[#F8FAFC] p-4">
                            <div class="text-xs font-semibold text-gray-500">Unsent Eligible</div>
                            <div class="mt-1 text-3xl font-bold text-[#213B73]">
                                {{ number_format($this->unsentEligibleSmsInviteesCount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-green-50 text-lg font-bold text-green-700">
                        3
                    </div>

                    <div>
                        <h3 class="font-bold text-[#111827]">Monitor Results</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600">
                            Track queued, sent, failed messages, and RSVP confirmation progress.
                        </p>

                        <div class="mt-4 rounded-xl bg-[#F8FAFC] p-4">
                            <div class="text-xs font-semibold text-gray-500">Sent SMS</div>
                            <div class="mt-1 text-3xl font-bold text-green-700">
                                {{ number_format($this->sentSmsCount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="grid gap-6 xl:grid-cols-3">

            {{-- Left Column --}}
            <div class="space-y-6 xl:col-span-2">

                {{-- Message Center Actions --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-[#111827]">
                                Message Actions
                            </h2>

                            <p class="mt-1 text-sm leading-6 text-gray-600">
                                Use these simple actions to prepare cards, send SMS, send WhatsApp, reminders, and monitor logs.
                            </p>
                        </div>

                        <div class="rounded-full bg-[#F8FAFC] px-4 py-2 text-xs font-semibold text-gray-600 ring-1 ring-gray-200">
                            SMS · WhatsApp · Reminders
                        </div>
                    </div>

                    {{-- Prepare --}}
                    <div class="mt-6">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">
                            Prepare
                        </h3>

                        <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <a
                                href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $this->record]) }}"
                                class="rounded-2xl bg-[#213B73] px-4 py-5 text-center text-sm font-bold text-white shadow-sm transition hover:bg-[#1D356A]"
                            >
                                <div class="text-base">Open Invitees</div>
                                <div class="mt-1 text-xs font-medium text-white/70">
                                    Manage guest list
                                </div>
                            </a>

                            <button
                                type="button"
                                wire:click="generateMissingCards"
                                @disabled($this->missingCardsCount === 0)
                                class="rounded-2xl px-4 py-5 text-center text-sm font-bold shadow-sm ring-1 transition
                                    {{ $this->missingCardsCount === 0
                                        ? 'cursor-not-allowed bg-gray-100 text-gray-400 ring-gray-200'
                                        : 'bg-[#FD9618] text-white ring-[#FD9618] hover:opacity-90' }}"
                            >
                                <div class="text-base">Generate Cards</div>
                                <div class="mt-1 text-xs font-medium opacity-80">
                                    {{ number_format($this->missingCardsCount) }} missing
                                </div>
                            </button>

                            <a
                                href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $this->record]) }}"
                                class="rounded-2xl bg-[#F8FAFC] px-4 py-5 text-center text-sm font-bold text-[#213B73] ring-1 ring-gray-200 transition hover:bg-gray-100"
                            >
                                <div class="text-base">Check RSVP</div>
                                <div class="mt-1 text-xs font-medium text-gray-500">
                                    Confirmed & pending
                                </div>
                            </a>
                        </div>
                    </div>

                    {{-- Send Invitations --}}
                    <div class="mt-8">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">
                            Send Invitations
                        </h3>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <button
                                type="button"
                                wire:click="sendSmsInvitations"
                                @disabled($this->unsentEligibleSmsInviteesCount === 0)
                                class="rounded-2xl px-4 py-5 text-center text-sm font-bold shadow-sm ring-1 transition
                                    {{ $this->unsentEligibleSmsInviteesCount === 0
                                        ? 'cursor-not-allowed bg-gray-100 text-gray-400 ring-gray-200'
                                        : 'bg-green-600 text-white ring-green-600 hover:bg-green-700' }}"
                            >
                                <div class="text-base">Send SMS</div>
                                <div class="mt-1 text-xs font-medium opacity-80">
                                    {{ number_format($this->unsentEligibleSmsInviteesCount) }} ready
                                </div>
                            </button>

                            <button
                                type="button"
                                wire:click="sendWhatsappInvitations"
                                class="rounded-2xl bg-[#213B73] px-4 py-5 text-center text-sm font-bold text-white shadow-sm ring-1 ring-[#213B73] transition hover:bg-[#1D356A]"
                            >
                                <div class="text-base">Send WhatsApp</div>
                                <div class="mt-1 text-xs font-medium text-white/70">
                                    Send card link
                                </div>
                            </button>
                        </div>
                    </div>

                    {{-- Reminders --}}
                    <div class="mt-8">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">
                            Reminders
                        </h3>

                        <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <button
                                type="button"
                                wire:click="sendRsvpReminderSms"
                                @disabled($this->pendingRsvpCount === 0)
                                class="rounded-2xl px-4 py-5 text-center text-sm font-bold shadow-sm ring-1 transition
                                    {{ $this->pendingRsvpCount === 0
                                        ? 'cursor-not-allowed bg-gray-100 text-gray-400 ring-gray-200'
                                        : 'bg-[#FD9618] text-white ring-[#FD9618] hover:opacity-90' }}"
                            >
                                <div class="text-base">RSVP Reminder</div>
                                <div class="mt-1 text-xs font-medium opacity-80">
                                    {{ number_format($this->pendingRsvpCount) }} pending
                                </div>
                            </button>

                            <button
                                type="button"
                                wire:click="sendEventDayReminderSms"
                                class="rounded-2xl bg-[#F8FAFC] px-4 py-5 text-center text-sm font-bold text-[#213B73] shadow-sm ring-1 ring-gray-200 transition hover:bg-gray-100"
                            >
                                <div class="text-base">Event Day Reminder</div>
                                <div class="mt-1 text-xs font-medium text-gray-500">
                                    Send final reminder
                                </div>
                            </button>

                            <button
                                type="button"
                                wire:click="sendThankYouSms"
                                class="rounded-2xl bg-[#F8FAFC] px-4 py-5 text-center text-sm font-bold text-[#213B73] shadow-sm ring-1 ring-gray-200 transition hover:bg-gray-100"
                            >
                                <div class="text-base">Thank You SMS</div>
                                <div class="mt-1 text-xs font-medium text-gray-500">
                                    After event
                                </div>
                            </button>
                        </div>
                    </div>

                    {{-- Monitoring --}}
                    <div class="mt-8">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">
                            Monitoring
                        </h3>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <a
                                href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $this->record]) }}"
                                class="rounded-2xl bg-[#F8FAFC] px-4 py-5 text-center text-sm font-bold text-[#213B73] ring-1 ring-gray-200 transition hover:bg-gray-100"
                            >
                                <div class="text-base">View SMS Logs</div>
                                <div class="mt-1 text-xs font-medium text-gray-500">
                                    Sent, queued, failed
                                </div>
                            </a>

                            <a
                                href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $this->record]) }}"
                                class="rounded-2xl bg-[#F8FAFC] px-4 py-5 text-center text-sm font-bold text-[#213B73] ring-1 ring-gray-200 transition hover:bg-gray-100"
                            >
                                <div class="text-base">View Message Logs</div>
                                <div class="mt-1 text-xs font-medium text-gray-500">
                                    WhatsApp / delivery
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Delivery Overview --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                    <div>
                        <h2 class="text-lg font-bold text-[#111827]">Delivery Overview</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            SMS queue and delivery status for this event.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-4">
                        <div class="rounded-2xl bg-[#F8FAFC] p-5 ring-1 ring-gray-200">
                            <div class="text-sm font-semibold text-gray-500">Eligible</div>
                            <div class="mt-2 text-3xl font-bold text-[#213B73]">
                                {{ number_format($this->eligibleSmsInviteesCount) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#F8FAFC] p-5 ring-1 ring-gray-200">
                            <div class="text-sm font-semibold text-gray-500">Queued</div>
                            <div class="mt-2 text-3xl font-bold text-[#FD9618]">
                                {{ number_format($this->queuedSmsCount) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#F8FAFC] p-5 ring-1 ring-gray-200">
                            <div class="text-sm font-semibold text-gray-500">Sent</div>
                            <div class="mt-2 text-3xl font-bold text-green-700">
                                {{ number_format($this->sentSmsCount) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#F8FAFC] p-5 ring-1 ring-gray-200">
                            <div class="text-sm font-semibold text-gray-500">Failed</div>
                            <div class="mt-2 text-3xl font-bold text-red-600">
                                {{ number_format($this->failedSmsCount) }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RSVP Overview --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                    <div>
                        <h2 class="text-lg font-bold text-[#111827]">RSVP Overview</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Monitor invitees who have confirmed and those still pending.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl bg-green-50 p-5 ring-1 ring-green-100">
                            <div class="text-sm font-semibold text-green-700">Confirmed / Attending</div>
                            <div class="mt-2 text-3xl font-bold text-green-700">
                                {{ number_format($this->attendingCount) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#FD9618]/10 p-5 ring-1 ring-[#FD9618]/20">
                            <div class="text-sm font-semibold text-[#FD9618]">Pending RSVP</div>
                            <div class="mt-2 text-3xl font-bold text-[#FD9618]">
                                {{ number_format($this->pendingRsvpCount) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#F8FAFC] p-5 ring-1 ring-gray-200">
                            <div class="text-sm font-semibold text-gray-500">Not Attending</div>
                            <div class="mt-2 text-3xl font-bold text-gray-700">
                                {{ number_format($this->notAttendingCount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="space-y-6">

                {{-- Readiness Checklist --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                    <h2 class="text-lg font-bold text-[#111827]">Readiness Checklist</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Confirm these before sending invitations.
                    </p>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-start gap-3 rounded-2xl bg-[#F8FAFC] p-4 ring-1 ring-gray-200">
                            <div class="mt-0.5 h-5 w-5 rounded-full {{ $this->inviteesCount > 0 ? 'bg-green-600' : 'bg-gray-300' }}"></div>
                            <div>
                                <div class="text-sm font-bold text-[#111827]">Invitees added</div>
                                <div class="text-xs text-gray-500">
                                    {{ number_format($this->inviteesCount) }} invitee(s)
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-2xl bg-[#F8FAFC] p-4 ring-1 ring-gray-200">
                            <div class="mt-0.5 h-5 w-5 rounded-full {{ $this->missingCardsCount === 0 && $this->inviteesCount > 0 ? 'bg-green-600' : 'bg-[#FD9618]' }}"></div>
                            <div>
                                <div class="text-sm font-bold text-[#111827]">Cards generated</div>
                                <div class="text-xs text-gray-500">
                                    {{ number_format($this->missingCardsCount) }} missing card(s)
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-2xl bg-[#F8FAFC] p-4 ring-1 ring-gray-200">
                            <div class="mt-0.5 h-5 w-5 rounded-full {{ $this->unsentEligibleSmsInviteesCount > 0 ? 'bg-green-600' : 'bg-gray-300' }}"></div>
                            <div>
                                <div class="text-sm font-bold text-[#111827]">Recipients ready</div>
                                <div class="text-xs text-gray-500">
                                    {{ number_format($this->unsentEligibleSmsInviteesCount) }} ready to send
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-2xl bg-[#F8FAFC] p-4 ring-1 ring-gray-200">
                            <div class="mt-0.5 h-5 w-5 rounded-full {{ $this->failedSmsCount === 0 ? 'bg-green-600' : 'bg-red-600' }}"></div>
                            <div>
                                <div class="text-sm font-bold text-[#111827]">No failed SMS</div>
                                <div class="text-xs text-gray-500">
                                    {{ number_format($this->failedSmsCount) }} failed message(s)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sending Rules --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                    <h2 class="text-lg font-bold text-[#111827]">Sending Rules</h2>

                    <div class="mt-5 space-y-3 text-sm leading-6 text-gray-600">
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] p-4">
                            SMS will only be queued for invitees with phone number, private link, and generated card.
                        </div>

                        <div class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] p-4">
                            Invitees with invitation SMS already queued, pending, sending, or sent are skipped automatically.
                        </div>

                        <div class="rounded-2xl border border-dashed border-gray-300 bg-[#F8FAFC] p-4">
                            Queue worker must be running for cards and SMS to process in the background.
                        </div>
                    </div>
                </div>

                {{-- WhatsApp / Message Logs --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-[#111827]">WhatsApp</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                WhatsApp sending will be handled inside this Message Center.
                            </p>
                        </div>

                        <div class="rounded-full bg-[#FD9618]/10 px-3 py-1 text-xs font-bold text-[#FD9618]">
                            Next
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-[#F8FAFC] p-4 ring-1 ring-gray-200">
                        <div class="text-sm font-semibold text-gray-500">Messages</div>
                        <div class="mt-2 text-3xl font-bold text-[#213B73]">
                            {{ number_format($this->sentMessagesCount) }}
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Sent, accepted, delivered, or read message logs.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>