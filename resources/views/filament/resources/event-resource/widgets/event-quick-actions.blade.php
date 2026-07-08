<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Event Quick Actions
        </x-slot>

        <x-slot name="description">
            Manage the main event workflow from one place.
        </x-slot>

        @php
            $event = $this->record ?? null;
            $eventId = $event?->getKey();

            $actions = [
                [
                    'title' => 'Invitees',
                    'description' => 'Manage guest list, card type, table number, and RSVP status.',
                    'icon' => 'heroicon-o-users',
                    'url' => $eventId ? url('/admin/events/' . $eventId . '?activeRelationManager=1') : '#',
                    'color' => '#213B73',
                ],
                [
                    'title' => 'Card Templates',
                    'description' => 'Upload invitation template and prepare card placeholders.',
                    'icon' => 'heroicon-o-photo',
                    'url' => $eventId ? url('/admin/events/' . $eventId . '?activeRelationManager=2') : '#',
                    'color' => '#FD9618',
                ],
                [
                    'title' => 'Generated Cards',
                    'description' => 'View personalized invitation cards generated for invitees.',
                    'icon' => 'heroicon-o-identification',
                    'url' => $eventId ? url('/admin/events/' . $eventId . '?activeRelationManager=3') : '#',
                    'color' => '#213B73',
                ],
                [
                    'title' => 'Message Center',
                    'description' => 'Send SMS, WhatsApp, reminders, and thank-you messages.',
                    'icon' => 'heroicon-o-envelope',
                    'url' => $eventId ? \App\Filament\Resources\EventResource::getUrl('send-message', ['record' => $event]) : '#',
                    'color' => '#FD9618',
                ],
                [
                    'title' => 'Message Logs',
                    'description' => 'Track SMS and WhatsApp delivery status and failures.',
                    'icon' => 'heroicon-o-inbox-stack',
                    'url' => $eventId ? url('/admin/events/' . $eventId . '?activeRelationManager=5') : '#',
                    'color' => '#213B73',
                ],
                [
                    'title' => 'RSVP Tracker',
                    'description' => 'See attending, not attending, pending, opened, and not opened.',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'url' => $eventId ? \App\Filament\Resources\EventResource::getUrl('invitee-responses', ['record' => $event]) : '#',
                    'color' => '#FD9618',
                ],
                [
                    'title' => 'Gate Check-in',
                    'description' => 'Scan QR codes or search by serial, phone, or name.',
                    'icon' => 'heroicon-o-qr-code',
                    'url' => $event ? route('gate.check-in.show', $event) : '#',
                    'color' => '#213B73',
                    'new_tab' => true,
                ],
                [
                    'title' => 'Reports',
                    'description' => 'Review event performance, check-ins, and communication reports.',
                    'icon' => 'heroicon-o-chart-bar',
                    'url' => $eventId ? url('/admin/reports?event_id=' . $eventId) : '#',
                    'color' => '#FD9618',
                    'new_tab' => true,
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($actions as $action)
                <a
                    href="{{ $action['url'] }}"
                    @if (($action['new_tab'] ?? false) === true) target="_blank" rel="noopener noreferrer" @endif
                    class="group rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" style="background: {{ $action['color'] }}1A; color: {{ $action['color'] }};">
                            @svg($action['icon'], 'h-6 w-6')
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">
                                {{ $action['title'] }}
                            </h3>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                {{ $action['description'] }}
                            </p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
