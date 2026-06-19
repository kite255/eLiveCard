<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#111827]">
                        Send Message
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Send SMS or WhatsApp invitations and reminders for this event.
                    </p>
                </div>

                <div class="rounded-lg bg-[#F8FAFC] px-4 py-3 text-sm">
                    <div class="font-semibold text-[#213B73]">
                        {{ $record->title ?? 'Untitled Event' }}
                    </div>

                    <div class="mt-1 text-gray-600">
                        {{ optional($record->event_date)->format('d M Y') ?? 'Date not set' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Invitees
                </div>

                <div class="mt-2 text-2xl font-bold text-[#111827]">
                    {{ $record->invitees()->count() }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Generated Cards
                </div>

                <div class="mt-2 text-2xl font-bold text-[#111827]">
                    {{ $record->generatedCards()->where('status', 'generated')->count() }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
                <div class="text-sm font-semibold text-gray-500">
                    Attending
                </div>

                <div class="mt-2 text-2xl font-bold text-[#111827]">
                    {{ $record->invitees()->where('rsvp_status', 'attending')->count() }}
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
            <h3 class="text-lg font-bold text-[#111827]">
                Message Center
            </h3>

            <p class="mt-2 text-sm text-gray-600">
                The page route is now fixed. Next, connect this page to your SMS and WhatsApp bulk-send actions.
            </p>

            <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-[#F8FAFC] p-4 text-sm text-gray-600">
                Use the Invitees table bulk actions for sending now, or add message forms here later.
            </div>
        </div>
    </div>
</x-filament-panels::page>
