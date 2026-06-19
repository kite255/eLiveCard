<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#111827]">
                        Send Message
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Send SMS or WhatsApp invitations, RSVP reminders, and event day reminders for this event.
                    </p>
                </div>

                <div class="rounded-lg bg-[#F8FAFC] px-4 py-3 text-sm">
                    <div class="font-semibold text-[#213B73]">
                        {{ $this->record->title ?? $this->record->name ?? 'Untitled Event' }}
                    </div>

                    <div class="mt-1 text-gray-600">
                        @if (! empty($this->record->event_date))
                            {{ \Carbon\Carbon::parse($this->record->event_date)->format('d M Y') }}
                        @else
                            Date not set
                        @endif
                    </div>

                    @if (! empty($this->record->venue_name))
                        <div class="mt-1 text-gray-600">
                            {{ $this->record->venue_name }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Invitees
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->inviteesCount }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Generated Cards
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->generatedCardsCount }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Attending
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->attendingCount }}
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Pending RSVP
                </div>

                <div class="mt-3 text-3xl font-bold text-[#FD9618]">
                    {{ $this->pendingRsvpCount }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Sent SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->sentSmsCount }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    WhatsApp / Messages
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->sentMessagesCount }}
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#111827]">
                        Message Center
                    </h3>

                    <p class="mt-2 text-sm text-gray-600">
                        Choose the message type you want to send. For now, message sending can continue from the Invitees bulk actions while we connect these buttons to queued jobs.
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <a
                    href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $this->record]) }}"
                    class="rounded-lg bg-[#213B73] px-4 py-3 text-center text-sm font-semibold text-white hover:bg-[#1D356A]"
                >
                    Open Invitees
                </a>

                <button
                    type="button"
                    class="rounded-lg bg-[#FD9618] px-4 py-3 text-sm font-semibold text-white hover:bg-[#E48600]"
                >
                    Send Invitations
                </button>

                <button
                    type="button"
                    class="rounded-lg border border-[#213B73] px-4 py-3 text-sm font-semibold text-[#213B73] hover:bg-[#F8FAFC]"
                >
                    RSVP Reminder
                </button>

                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-3 text-sm font-semibold text-[#111827] hover:bg-[#F8FAFC]"
                >
                    Event Day Reminder
                </button>
            </div>

            <div class="mt-5 rounded-lg border border-dashed border-gray-300 bg-[#F8FAFC] p-4 text-sm text-gray-600">
                Next implementation: connect each button to SMS / WhatsApp sending jobs, save logs, and prevent duplicate sending.
            </div>
        </div>
    </div>
</x-filament-panels::page>