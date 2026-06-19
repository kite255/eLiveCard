<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#111827]">
                        Send Message
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Send SMS invitations, check generated cards, and monitor message delivery for this event.
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

        {{-- Main Statistics --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Total Invitees
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
                    Missing Cards
                </div>

                <div class="mt-3 text-3xl font-bold text-[#FD9618]">
                    {{ $this->missingCardsCount }}
                </div>
            </div>
        </div>

        {{-- SMS Readiness --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Eligible for SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->eligibleSmsInviteesCount }}
                </div>

                <p class="mt-2 text-xs text-gray-500">
                    Has phone number, private link, and generated card.
                </p>
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
                    Failed SMS
                </div>

                <div class="mt-3 text-3xl font-bold text-red-600">
                    {{ $this->failedSmsCount }}
                </div>
            </div>
        </div>

        {{-- RSVP / WhatsApp --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Attending
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->attendingCount }}
                </div>
            </div>

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
                    WhatsApp / Messages
                </div>

                <div class="mt-3 text-3xl font-bold text-[#213B73]">
                    {{ $this->sentMessagesCount }}
                </div>
            </div>
        </div>

        {{-- Message Center --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#111827]">
                        Message Center
                    </h3>

                    <p class="mt-2 text-sm text-gray-600">
                        Use the top-right page actions to generate missing cards and send SMS invitations.
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

                <div class="rounded-lg bg-[#F8FAFC] px-4 py-3 text-center text-sm font-semibold text-[#213B73] ring-1 ring-gray-200">
                    Generate Missing Cards
                </div>

                <div class="rounded-lg bg-[#F8FAFC] px-4 py-3 text-center text-sm font-semibold text-[#213B73] ring-1 ring-gray-200">
                    Send SMS Invitations
                </div>

                <div class="rounded-lg bg-[#F8FAFC] px-4 py-3 text-center text-sm font-semibold text-gray-500 ring-1 ring-gray-200">
                    WhatsApp Coming Next
                </div>
            </div>

            <div class="mt-5 rounded-lg border border-dashed border-gray-300 bg-[#F8FAFC] p-4 text-sm text-gray-600">
                <strong class="text-[#111827]">Live flow:</strong>
                generate missing cards first, then send SMS invitations. SMS will only be queued for invitees with phone number, private link, and generated card.
            </div>
        </div>
    </div>
</x-filament-panels::page>