<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Event Selection
            </x-slot>

            <x-slot name="description">
                Select an event to view RSVP, SMS, and check-in report summary.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Select Event
                    </label>

                    <select
                        wire:model.live="eventId"
                        class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        @foreach ($this->events as $event)
                            <option value="{{ $event->id }}">
                                {{ $event->title }}
                                {{ $event->event_date ? '- ' . \Carbon\Carbon::parse($event->event_date)->format('d M Y') : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($this->selectedEvent)
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Selected Event
                        </div>

                        <div class="mt-1 font-semibold text-gray-950 dark:text-white">
                            {{ $this->selectedEvent->title }}
                        </div>

                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $this->selectedEvent->event_date ? \Carbon\Carbon::parse($this->selectedEvent->event_date)->format('d M Y') : 'TBA' }}
                            @if ($this->selectedEvent->start_time)
                                · {{ \Carbon\Carbon::parse($this->selectedEvent->start_time)->format('h:i A') }}
                            @endif
                            @if ($this->selectedEvent->venue_name)
                                · {{ $this->selectedEvent->venue_name }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>

        @php
            $report = $this->report;

            $cards = [
                [
                    'label' => 'Total Invitees',
                    'value' => $report['total_invitees'],
                    'description' => 'People invited',
                    'color' => 'gray',
                ],
                [
                    'label' => 'Allowed Guests',
                    'value' => $report['total_allowed_guests'],
                    'description' => 'Maximum guests allowed',
                    'color' => 'gray',
                ],
                [
                    'label' => 'Confirmed Guests',
                    'value' => $report['confirmed_guests'],
                    'description' => 'Expected from RSVP',
                    'color' => 'success',
                ],
                [
                    'label' => 'Checked-in Guests',
                    'value' => $report['checked_in_guests'],
                    'description' => 'Already checked in',
                    'color' => 'success',
                ],
                [
                    'label' => 'RSVP Pending',
                    'value' => $report['rsvp_pending'],
                    'description' => 'Need reminder',
                    'color' => 'warning',
                ],
                [
                    'label' => 'Attending',
                    'value' => $report['attending'],
                    'description' => 'Confirmed attending',
                    'color' => 'success',
                ],
                [
                    'label' => 'Not Attending',
                    'value' => $report['not_attending'],
                    'description' => 'Declined invitation',
                    'color' => 'danger',
                ],
                [
                    'label' => 'Maybe',
                    'value' => $report['maybe'],
                    'description' => 'Not fully confirmed',
                    'color' => 'warning',
                ],
                [
                    'label' => 'Invitation SMS Sent',
                    'value' => $report['invitation_sms_sent'],
                    'description' => 'Invitation messages',
                    'color' => 'info',
                ],
                [
                    'label' => 'Reminder SMS Sent',
                    'value' => $report['reminder_sms_sent'],
                    'description' => 'Reminder messages',
                    'color' => 'info',
                ],
                [
                    'label' => 'Final SMS Sent',
                    'value' => $report['final_sms_sent'],
                    'description' => 'Event-day messages',
                    'color' => 'info',
                ],
                [
                    'label' => 'SMS Failed',
                    'value' => $report['sms_failed'],
                    'description' => 'Needs attention',
                    'color' => 'danger',
                ],
            ];
        @endphp

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <x-filament::section>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $card['label'] }}
                            </div>

                            <x-filament::badge :color="$card['color']">
                                {{ $card['description'] }}
                            </x-filament::badge>
                        </div>

                        <div class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                            {{ $card['value'] }}
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">
                    RSVP Response Rate
                </x-slot>

                <x-slot name="description">
                    Percentage of invitees who responded to RSVP.
                </x-slot>

                <div class="space-y-4">
                    <div class="text-4xl font-bold text-primary-600">
                        {{ $report['rsvp_response_rate'] }}%
                    </div>

                    <div class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div
                            class="h-full rounded-full bg-primary-600"
                            style="width: {{ min(100, $report['rsvp_response_rate']) }}%"
                        ></div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Check-in Attendance Rate
                </x-slot>

                <x-slot name="description">
                    Percentage of allowed guests already checked in.
                </x-slot>

                <div class="space-y-4">
                    <div class="text-4xl font-bold text-primary-600">
                        {{ $report['attendance_rate'] }}%
                    </div>

                    <div class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div
                            class="h-full rounded-full bg-primary-600"
                            style="width: {{ min(100, $report['attendance_rate']) }}%"
                        ></div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">
                    RSVP Breakdown
                </x-slot>

                <x-slot name="description">
                    Summary of invitees grouped by RSVP status.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 px-4 text-right">Invitees</th>
                                <th class="py-3 pl-4 text-right">Confirmed Guests</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->rsvpSummary as $row)
                                <tr>
                                    <td class="py-3 pr-4 font-medium text-gray-950 dark:text-white">
                                        {{ $row['status'] }}
                                    </td>

                                    <td class="py-3 px-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['count'] }}
                                    </td>

                                    <td class="py-3 pl-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['confirmed_guests'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Card Type Breakdown
                </x-slot>

                <x-slot name="description">
                    Attendance summary grouped by card type.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="py-3 pr-4">Card Type</th>
                                <th class="py-3 px-4 text-right">Invitees</th>
                                <th class="py-3 px-4 text-right">Allowed</th>
                                <th class="py-3 px-4 text-right">Attending</th>
                                <th class="py-3 pl-4 text-right">Checked-in</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($this->cardTypeSummary as $row)
                                <tr>
                                    <td class="py-3 pr-4 font-medium text-gray-950 dark:text-white">
                                        {{ $row['card_type'] }}
                                    </td>

                                    <td class="py-3 px-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['invitees'] }}
                                    </td>

                                    <td class="py-3 px-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['allowed_guests'] }}
                                    </td>

                                    <td class="py-3 px-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['attending'] }}
                                    </td>

                                    <td class="py-3 pl-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['checked_in'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-500 dark:text-gray-400">
                                        No invitees found for this event.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>