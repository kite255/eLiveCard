<?php

namespace App\Filament\Resources\EventResource\Widgets;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;

class EventQuickActions extends Widget
{
    protected static string $view = 'filament.resources.event-resource.widgets.event-quick-actions';

    protected int|string|array $columnSpan = 'full';

    public ?Event $record = null;

    public function getViewData(): array
    {
        $event = $this->record;

        if (! $event) {
            return [
                'actions' => [],
            ];
        }

        return [
            'actions' => [
                [
                    'title' => 'Invitees',
                    'description' => 'Manage guest list, card type, table, RSVP, and phone numbers.',
                    'icon' => 'heroicon-o-users',
                    'color' => '#213B73',
                    'url' => EventResource::getUrl('view', ['record' => $event]),
                    'hint' => number_format((int) ($event->invitees_count ?? 0)) . ' invitees',
                ],
                [
                    'title' => 'Card Templates',
                    'description' => 'Upload card design and prepare placeholders for personalized cards.',
                    'icon' => 'heroicon-o-photo',
                    'color' => '#FD9618',
                    'url' => EventResource::getUrl('view', ['record' => $event]),
                    'hint' => 'Design cards',
                ],
                [
                    'title' => 'Generated Cards',
                    'description' => 'Review personalized invitation cards generated for invitees.',
                    'icon' => 'heroicon-o-identification',
                    'color' => '#213B73',
                    'url' => EventResource::getUrl('view', ['record' => $event]),
                    'hint' => number_format((int) ($event->generated_cards_count ?? 0)) . ' cards',
                ],
                [
                    'title' => 'Message Center',
                    'description' => 'Send SMS, WhatsApp invitations, reminders, and thank-you messages.',
                    'icon' => 'heroicon-o-envelope',
                    'color' => '#FD9618',
                    'url' => EventResource::getUrl('send-message', ['record' => $event]),
                    'hint' => 'Send messages',
                ],
                [
                    'title' => 'Message Logs',
                    'description' => 'Track sent, failed, delivered, and provider error messages.',
                    'icon' => 'heroicon-o-inbox-stack',
                    'color' => '#213B73',
                    'url' => EventResource::getUrl('view', ['record' => $event]),
                    'hint' => number_format((int) ($event->communication_failed_count ?? 0)) . ' failed',
                ],
                [
                    'title' => 'RSVP Tracker',
                    'description' => 'See attending, not attending, pending, opened, and not opened invitees.',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'color' => '#FD9618',
                    'url' => EventResource::getUrl('invitee-responses', ['record' => $event]),
                    'hint' => 'Track responses',
                ],
                [
                    'title' => 'Gate Check-in',
                    'description' => 'Scan QR codes, search invitees, and enforce allowed guest limit.',
                    'icon' => 'heroicon-o-qr-code',
                    'color' => '#213B73',
                    'url' => route('gate.check-in.show', $event),
                    'hint' => number_format((int) ($event->check_ins_count ?? 0)) . ' checked in',
                    'new_tab' => true,
                ],
                [
                    'title' => 'Reports',
                    'description' => 'Review attendance, delivery, RSVP, check-in, and guest summaries.',
                    'icon' => 'heroicon-o-chart-bar',
                    'color' => '#FD9618',
                    'url' => url('/admin/reports?event_id=' . $event->id),
                    'hint' => 'View reports',
                    'new_tab' => true,
                ],
            ],
        ];
    }
}
