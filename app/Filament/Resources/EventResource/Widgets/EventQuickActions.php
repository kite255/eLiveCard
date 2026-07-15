<?php

namespace App\Filament\Resources\EventResource\Widgets;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventQuickActions extends Widget
{
    protected static string $view =
        'filament.resources.event-resource.widgets.event-quick-actions';

    protected int|string|array $columnSpan = 'full';

    public ?Event $record = null;

    private const RELATION_CARD_TYPES = 1;
    private const RELATION_INVITEES = 2;
    private const RELATION_INVITEE_UPLOADS = 3;
    private const RELATION_CARD_TEMPLATES = 4;
    private const RELATION_GENERATED_CARDS = 5;
    private const RELATION_MESSAGE_TEMPLATES = 6;
    private const RELATION_MESSAGE_LOGS = 7;
    private const RELATION_CHECK_INS = 9;

    public function getViewData(): array
    {
        $event = $this->record;

        if (! $event) {
            return ['actions' => []];
        }

        $inviteesCount = $this->relationshipCount($event, 'invitees');
        $generatedCardsCount = $this->readyGeneratedCardsCount($event);
        $checkInsCount = $this->relationshipCount($event, 'checkIns');
        $failedMessagesCount = $this->failedMessagesCount($event);
        $pendingRsvpCount = $this->pendingRsvpCount($event);
        $pendingApprovalsCount = $this->pendingApprovalsCount($event);
        $cardTypesCount = $this->relationshipCount($event, 'cardTypes');
        $cardTemplatesCount = $this->relationshipCount($event, 'cardTemplates');
        $messageTemplatesCount = $this->relationshipCount($event, 'messageTemplates');

        $actions = [
            [
                'key' => 'invitees',
                'title' => 'Manage Invitees',
                'description' => 'Add, import, edit, categorise, and assign tables to invitees.',
                'icon' => 'heroicon-o-users',
                'accent' => 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_INVITEES),
                'hint' => number_format($inviteesCount).' invitees',
                'badge' => $inviteesCount,
                'priority' => 'primary',
                'visible' => $this->canManageEvent($event),
            ],
            [
                'key' => 'card-types',
                'title' => 'Card Types',
                'description' => 'Configure Single, Double, Family, VIP, VVIP, Committee, and custom guest limits.',
                'icon' => 'heroicon-o-rectangle-stack',
                'accent' => 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_CARD_TYPES),
                'hint' => number_format($cardTypesCount).' card types',
                'badge' => $cardTypesCount,
                'priority' => 'secondary',
                'visible' => $this->canManageEvent($event),
            ],
            [
                'key' => 'card-templates',
                'title' => 'Card Templates',
                'description' => 'Upload invitation designs and configure dynamic placeholders.',
                'icon' => 'heroicon-o-photo',
                'accent' => 'orange',
                'url' => $this->relationManagerUrl($event, self::RELATION_CARD_TEMPLATES),
                'hint' => number_format($cardTemplatesCount).' templates',
                'badge' => $cardTemplatesCount,
                'priority' => 'secondary',
                'visible' => $this->canManageEvent($event),
            ],
            [
                'key' => 'generated-cards',
                'title' => 'Generated Cards',
                'description' => 'Generate, review, download, and resend personalised invitation cards.',
                'icon' => 'heroicon-o-identification',
                'accent' => 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_GENERATED_CARDS),
                'hint' => number_format($generatedCardsCount).' cards ready',
                'badge' => $generatedCardsCount,
                'priority' => 'secondary',
                'visible' => $this->canManageEvent($event),
            ],
            [
                'key' => 'message-center',
                'title' => 'Message Center',
                'description' => 'Send SMS, WhatsApp invitations, reminders, and event follow-ups.',
                'icon' => 'heroicon-o-envelope',
                'accent' => 'orange',
                'url' => EventResource::getUrl('send-message', ['record' => $event]),
                'hint' => 'Send messages',
                'badge' => null,
                'priority' => 'primary',
                'visible' => $this->canSendMessages(),
            ],
            [
                'key' => 'message-templates',
                'title' => 'Message Templates',
                'description' => 'Manage reusable SMS and WhatsApp invitation and reminder templates.',
                'icon' => 'heroicon-o-document-text',
                'accent' => 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_MESSAGE_TEMPLATES),
                'hint' => number_format($messageTemplatesCount).' templates',
                'badge' => $messageTemplatesCount,
                'priority' => 'secondary',
                'visible' => $this->canSendMessages(),
            ],
            [
                'key' => 'delivery-logs',
                'title' => 'Delivery Logs',
                'description' => 'Review sent, delivered, pending, failed, and provider response logs.',
                'icon' => 'heroicon-o-inbox-stack',
                'accent' => $failedMessagesCount > 0 ? 'red' : 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_MESSAGE_LOGS),
                'hint' => $failedMessagesCount > 0
                    ? number_format($failedMessagesCount).' failed'
                    : 'No failed messages',
                'badge' => $failedMessagesCount,
                'priority' => $failedMessagesCount > 0 ? 'attention' : 'secondary',
                'visible' => $this->canSendMessages(),
            ],
            [
                'key' => 'rsvp-tracker',
                'title' => 'RSVP Tracker',
                'description' => 'Monitor attending, declined, pending, opened, and unanswered invitations.',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'accent' => $pendingRsvpCount > 0 ? 'orange' : 'blue',
                'url' => EventResource::getUrl('invitee-responses', ['record' => $event]),
                'hint' => number_format($pendingRsvpCount).' pending',
                'badge' => $pendingRsvpCount,
                'priority' => $pendingRsvpCount > 0 ? 'attention' : 'secondary',
                'visible' => $this->canManageEvent($event),
            ],
            [
                'key' => 'approvals',
                'title' => 'Photo & Wish Approvals',
                'description' => 'Review invitee photos and wishes before they appear publicly.',
                'icon' => 'heroicon-o-shield-check',
                'accent' => $pendingApprovalsCount > 0 ? 'orange' : 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_INVITEE_UPLOADS),
                'hint' => number_format($pendingApprovalsCount).' pending',
                'badge' => $pendingApprovalsCount,
                'priority' => $pendingApprovalsCount > 0 ? 'attention' : 'secondary',
                'visible' => $this->canManageEvent($event),
            ],
            [
                'key' => 'gate-check-in',
                'title' => 'Gate Check-in',
                'description' => 'Scan QR codes, search invitees, and enforce guest limits at the gate.',
                'icon' => 'heroicon-o-qr-code',
                'accent' => 'blue',
                'url' => route('gate.check-in.show', $event),
                'hint' => number_format($checkInsCount).' check-ins',
                'badge' => $checkInsCount,
                'new_tab' => true,
                'priority' => 'primary',
                'visible' => $this->canAccessEvent($event),
            ],
            [
                'key' => 'check-in-records',
                'title' => 'Check-in Records',
                'description' => 'Review attendee entries, guest totals, gate users, and check-in times.',
                'icon' => 'heroicon-o-clipboard-document-check',
                'accent' => 'blue',
                'url' => $this->relationManagerUrl($event, self::RELATION_CHECK_INS),
                'hint' => number_format($checkInsCount).' records',
                'badge' => $checkInsCount,
                'priority' => 'secondary',
                'visible' => $this->canAccessEvent($event),
            ],
            [
                'key' => 'reports',
                'title' => 'Reports',
                'description' => 'Open RSVP, messaging, attendance, card, and check-in reports.',
                'icon' => 'heroicon-o-chart-bar-square',
                'accent' => 'orange',
                'url' => url('/admin/reports?event_id='.$event->getKey()),
                'hint' => 'View reports',
                'badge' => null,
                'new_tab' => true,
                'priority' => 'secondary',
                'visible' => $this->canViewReports(),
            ],
        ];

        return [
            'actions' => array_values(array_filter(
                $actions,
                fn (array $action): bool => (bool) ($action['visible'] ?? true),
            )),
        ];
    }

    private function relationManagerUrl(Event $event, int $position): string
    {
        return EventResource::getUrl('view', [
            'record' => $event,
            'activeRelationManager' => $position,
        ]);
    }

    private function relationshipCount(Event $event, string $relationship): int
    {
        if (! method_exists($event, $relationship)) {
            return 0;
        }

        try {
            $relation = $event->{$relationship}();

            if (! $relation instanceof Relation) {
                return 0;
            }

            return (int) $relation->count();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private function readyGeneratedCardsCount(Event $event): int
    {
        if (! method_exists($event, 'generatedCards') || ! Schema::hasTable('generated_cards')) {
            return 0;
        }

        try {
            $query = $event->generatedCards();

            if (Schema::hasColumn('generated_cards', 'status')) {
                $query->whereIn('status', ['generated', 'sent']);
            }

            return (int) $query->count();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private function failedMessagesCount(Event $event): int
    {
        $failedStatuses = ['failed', 'rejected', 'error'];

        try {
            if (method_exists($event, 'messageLogs') && Schema::hasTable('message_logs')) {
                $query = $event->messageLogs();

                if (Schema::hasColumn('message_logs', 'status')) {
                    $query->whereIn('status', $failedStatuses);
                }

                return (int) $query->count();
            }

            if (method_exists($event, 'smsLogs') && Schema::hasTable('sms_logs')) {
                $query = $event->smsLogs();

                if (Schema::hasColumn('sms_logs', 'status')) {
                    $query->whereIn('status', $failedStatuses);
                }

                return (int) $query->count();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return 0;
    }

    private function pendingRsvpCount(Event $event): int
    {
        if (! method_exists($event, 'invitees') || ! Schema::hasTable('invitees') || ! Schema::hasColumn('invitees', 'rsvp_status')) {
            return 0;
        }

        try {
            return (int) $event->invitees()
                ->where(function ($query): void {
                    $query
                        ->whereNull('rsvp_status')
                        ->orWhere('rsvp_status', '')
                        ->orWhere('rsvp_status', 'pending');
                })
                ->count();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private function pendingApprovalsCount(Event $event): int
    {
        if (! Schema::hasTable('invitee_uploads')) {
            return 0;
        }

        try {
            $query = DB::table('invitee_uploads');

            if (Schema::hasColumn('invitee_uploads', 'event_id')) {
                $query->where('event_id', $event->getKey());
            } elseif (Schema::hasColumn('invitee_uploads', 'invitee_id') && method_exists($event, 'invitees')) {
                $query->whereIn('invitee_id', $event->invitees()->select('id'));
            } else {
                return 0;
            }

            if (Schema::hasColumn('invitee_uploads', 'status')) {
                $query->where(function ($query): void {
                    $query
                        ->whereNull('status')
                        ->orWhere('status', '')
                        ->orWhere('status', 'pending');
                });
            } elseif (Schema::hasColumn('invitee_uploads', 'is_approved')) {
                $query->where(function ($query): void {
                    $query
                        ->whereNull('is_approved')
                        ->orWhere('is_approved', false);
                });
            }

            return (int) $query->count();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    private function canManageEvent(Event $event): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canManageEvent')) {
            return (bool) $user->canManageEvent($event);
        }

        return $user->can('update', $event);
    }

    private function canAccessEvent(Event $event): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canAccessEvent')) {
            return (bool) $user->canAccessEvent($event);
        }

        return $user->can('view', $event);
    }

    private function canSendMessages(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canSendMessages')) {
            return (bool) $user->canSendMessages();
        }

        return true;
    }

    private function canViewReports(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canViewReports')) {
            return (bool) $user->canViewReports();
        }

        return true;
    }
}
