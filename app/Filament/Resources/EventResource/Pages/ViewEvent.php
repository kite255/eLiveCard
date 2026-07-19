<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Exports\EventSummaryExport;
use App\Filament\Resources\EventResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static string $view =
        'filament.resources.event-resource.pages.view-event';

    protected static ?string $title = 'Event Workspace';

    private const RELATION_ASSIGNED_USERS = 0;
    private const RELATION_CARD_TYPES = 1;
    private const RELATION_INVITEES = 2;
    private const RELATION_INVITEE_UPLOADS = 3;
    private const RELATION_CARD_TEMPLATES = 4;
    private const RELATION_GENERATED_CARDS = 5;
    private const RELATION_MESSAGE_TEMPLATES = 6;
    private const RELATION_MESSAGE_LOGS = 7;
    private const RELATION_SMS_LOGS = 8;
    private const RELATION_CHECK_INS = 9;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return 'Event Workspace';
    }

    public function getHeading(): string
    {
        return 'Event Workspace';
    }

    public function getSubheading(): ?string
    {
        return 'Manage your event, invitees, cards, messages, RSVP responses, check-ins, and reports.';
    }

    public function getEventName(): string
    {
        return (string) (
            $this->record->title
            ?? $this->record->name
            ?? 'Untitled Event'
        );
    }

    public function getEventTypeLabel(): string
    {
        $eventType = $this->record->event_type;

        if (blank($eventType)) {
            return 'Social Event';
        }

        $model = EventResource::getModel();
        $eventTypes = method_exists($model, 'eventTypes')
            ? $model::eventTypes()
            : [];

        return $eventTypes[$eventType]
            ?? Str::headline((string) $eventType);
    }

    public function getFormattedEventDate(): string
    {
        if (blank($this->record->event_date)) {
            return 'Date not set';
        }

        return Carbon::parse($this->record->event_date)->format('d M Y');
    }

    public function getFormattedEventTime(): string
    {
        $startTime = $this->formatTime($this->record->start_time);
        $endTime = $this->formatTime($this->record->end_time);

        if ($startTime && $endTime) {
            return "{$startTime} – {$endTime}";
        }

        return $startTime ?? $endTime ?? 'Time not set';
    }

    protected function formatTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('h:i A');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function getVenueName(): string
    {
        return (string) (
            $this->record->venue_name
            ?? $this->record->venue
            ?? 'Venue not set'
        );
    }

    public function getVenueAddress(): string
    {
        return (string) (
            $this->record->venue_address
            ?? $this->record->address
            ?? 'Address not set'
        );
    }

    public function getEventOwnerName(): string
    {
        return (string) (
            $this->record->user?->name
            ?? $this->record->owner?->name
            ?? 'Not assigned'
        );
    }

    public function getOrganizerName(): string
    {
        return (string) (
            $this->record->contact_person_name
            ?? $this->record->organizer_name
            ?? $this->getEventOwnerName()
        );
    }

    public function getOrganizerPhone(): string
    {
        return (string) (
            $this->record->organizer_phone
            ?? $this->record->contact_person_phone
            ?? $this->record->contact_phone
            ?? $this->record->phone
            ?? 'Not provided'
        );
    }

    public function getDressCode(): string
    {
        return (string) ($this->record->dress_code ?? 'Not specified');
    }

    public function getStatusLabel(): string
    {
        return (string) (
            $this->record->display_status_label
            ?? Str::headline((string) ($this->record->status ?? 'draft'))
        );
    }

    public function getStatusColor(): string
    {
        $status = (string) (
            $this->record->display_status
            ?? $this->record->status
            ?? 'draft'
        );

        return match ($status) {
            'active' => 'success',
            'upcoming' => 'warning',
            'completed' => 'info',
            'cancelled' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        $status = (string) (
            $this->record->display_status
            ?? $this->record->status
            ?? 'draft'
        );

        return match ($status) {
            'active' => 'heroicon-m-bolt',
            'upcoming' => 'heroicon-m-clock',
            'completed' => 'heroicon-m-check-circle',
            'cancelled' => 'heroicon-m-x-circle',
            'draft' => 'heroicon-m-pencil-square',
            default => 'heroicon-m-information-circle',
        };
    }

    public function getEventImageUrl(): ?string
    {
        $imagePath = $this->record->cover_image
            ?? $this->record->cover_image_path
            ?? $this->record->banner_image
            ?? $this->record->image
            ?? null;

        if (blank($imagePath)) {
            return null;
        }

        $imagePath = (string) $imagePath;

        if (Str::startsWith($imagePath, ['http://', 'https://', '/'])) {
            return $imagePath;
        }

        return Storage::disk('public')->url($imagePath);
    }

    public function hasLocation(): bool
    {
        return filled($this->record->google_maps_link);
    }

    public function canEditEvent(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canManageEvent')) {
            return (bool) $user->canManageEvent($this->record);
        }

        return $user->can('update', $this->record);
    }

    public function canSendEventMessages(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canSendMessages')) {
            return (bool) $user->canSendMessages();
        }

        return $this->canEditEvent();
    }

    public function canViewReports(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canViewReports')) {
            return (bool) $user->canViewReports();
        }

        return $user->can('view', $this->record);
    }

    public function getEditEventUrl(): string
    {
        return EventResource::getUrl('edit', ['record' => $this->record]);
    }

    public function getMessageCenterUrl(): string
    {
        return EventResource::getUrl('send-message', ['record' => $this->record]);
    }

    public function getInviteeResponsesUrl(): string
    {
        return EventResource::getUrl('invitee-responses', ['record' => $this->record]);
    }

    public function getGateCheckInUrl(): string
    {
        return route('gate.check-in.entry', [
            'event' => $this->record->getKey(),
        ]);
    }

    public function getCheckInDashboardUrl(): string
    {
        return EventResource::getUrl('check-in-dashboard', [
            'record' => $this->record,
        ]);
    }

    public function getReportsUrl(): string
    {
        return url('/admin/reports?event_id='.$this->record->getKey());
    }

    public function getLocationUrl(): ?string
    {
        return $this->hasLocation()
            ? (string) $this->record->google_maps_link
            : null;
    }

    protected function relationManagerUrl(int $position): string
    {
        return EventResource::getUrl('view', [
            'record' => $this->record,
            'activeRelationManager' => $position,
        ]);
    }

    public function getAssignedUsersUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_ASSIGNED_USERS);
    }

    public function getCardTypesUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_CARD_TYPES);
    }

    public function getInviteesUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_INVITEES);
    }

    public function getInviteeUploadsUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_INVITEE_UPLOADS);
    }

    public function getCardTemplatesUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_CARD_TEMPLATES);
    }

    public function getGeneratedCardsUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_GENERATED_CARDS);
    }

    public function getMessageTemplatesUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_MESSAGE_TEMPLATES);
    }

    public function getDeliveryLogsUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_MESSAGE_LOGS);
    }

    public function getSmsLogsUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_SMS_LOGS);
    }

    public function getCheckInsUrl(): string
    {
        return $this->relationManagerUrl(self::RELATION_CHECK_INS);
    }


    /*
    |--------------------------------------------------------------------------
    | Workspace statistics
    |--------------------------------------------------------------------------
    */

    public function getWorkspaceStats(): array
    {
        $event = $this->record;

        $invitees = $this->safeRelationshipCount('invitees');
        $checkInTransactions = $this->safeRelationshipCount('checkIns');

        $checkedInInvitees = 0;
        $guestsCheckedIn = 0;
        $totalAllowedGuests = 0;
        $attending = 0;
        $pendingRsvp = 0;
        $generatedCards = 0;
        $messagesSent = 0;
        $messagesFailed = 0;

        try {
            if (
                method_exists($event, 'invitees')
                && Schema::hasTable('invitees')
            ) {
                if (Schema::hasColumn('invitees', 'allowed_guests')) {
                    $totalAllowedGuests = (int) $event
                        ->invitees()
                        ->sum('allowed_guests');
                }

                if (Schema::hasColumn('invitees', 'checked_in_count')) {
                    $checkedInInvitees = (int) $event
                        ->invitees()
                        ->where('checked_in_count', '>', 0)
                        ->count();

                    $guestsCheckedIn = (int) $event
                        ->invitees()
                        ->sum('checked_in_count');
                }

                if (Schema::hasColumn('invitees', 'rsvp_status')) {
                    $attending = (int) $event
                        ->invitees()
                        ->where('rsvp_status', 'attending')
                        ->count();

                    $pendingRsvp = (int) $event
                        ->invitees()
                        ->where(function (Builder $query): void {
                            $query
                                ->whereNull('rsvp_status')
                                ->orWhere('rsvp_status', '')
                                ->orWhere('rsvp_status', 'pending');
                        })
                        ->count();
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            if (
                method_exists($event, 'generatedCards')
                && Schema::hasTable('generated_cards')
            ) {
                $query = $event->generatedCards();

                if (Schema::hasColumn('generated_cards', 'status')) {
                    $query->whereIn('status', ['generated', 'sent']);
                }

                $generatedCards = (int) $query->count();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        [$messagesSent, $messagesFailed] = $this->messageTotals();

        $responded = max(0, $invitees - $pendingRsvp);

        return [
            'invitees' => $invitees,
            'attending' => $attending,
            'pending_rsvp' => $pendingRsvp,
            'responded' => $responded,
            'rsvp_rate' => $this->percentage($responded, $invitees),
            'attending_rate' => $this->percentage($attending, $invitees),
            'generated_cards' => $generatedCards,
            'cards_rate' => $this->percentage($generatedCards, $invitees),
            'check_ins' => $checkedInInvitees,
            'check_in_transactions' => $checkInTransactions,
            'guests_checked_in' => $guestsCheckedIn,
            'total_allowed_guests' => $totalAllowedGuests,
            'check_in_rate' => $this->percentage($guestsCheckedIn, $totalAllowedGuests),
            'messages_sent' => $messagesSent,
            'messages_failed' => $messagesFailed,
        ];
    }

    protected function safeRelationshipCount(string $relationship): int
    {
        if (! method_exists($this->record, $relationship)) {
            return 0;
        }

        try {
            return (int) $this->record->{$relationship}()->count();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    protected function messageTotals(): array
    {
        $sentStatuses = ['sent', 'delivered', 'read'];
        $failedStatuses = ['failed', 'rejected', 'error'];

        try {
            if (
                method_exists($this->record, 'messageLogs')
                && Schema::hasTable('message_logs')
            ) {
                $sentQuery = $this->record->messageLogs();
                $failedQuery = $this->record->messageLogs();

                if (Schema::hasColumn('message_logs', 'status')) {
                    $sentQuery->whereIn('status', $sentStatuses);
                    $failedQuery->whereIn('status', $failedStatuses);
                }

                return [
                    (int) $sentQuery->count(),
                    (int) $failedQuery->count(),
                ];
            }

            if (
                method_exists($this->record, 'smsLogs')
                && Schema::hasTable('sms_logs')
            ) {
                $sentQuery = $this->record->smsLogs();
                $failedQuery = $this->record->smsLogs();

                if (Schema::hasColumn('sms_logs', 'status')) {
                    $sentQuery->whereIn('status', ['sent', 'delivered']);
                    $failedQuery->whereIn('status', $failedStatuses);
                }

                return [
                    (int) $sentQuery->count(),
                    (int) $failedQuery->count(),
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return [0, 0];
    }

    protected function percentage(int $value, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return min(
            100,
            max(0, (int) round(($value / $total) * 100)),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Workspace cards and quick actions
    |--------------------------------------------------------------------------
    */

    public function getWorkspaceStatCards(): array
    {
        $stats = $this->getWorkspaceStats();

        return [
            [
                'label' => 'Total Invitees',
                'value' => number_format($stats['invitees']),
                'description' => 'All invitees',
                'icon' => 'heroicon-o-users',
                'tone' => 'blue',
            ],
            [
                'label' => 'Attending',
                'value' => number_format($stats['attending']),
                'description' => $stats['attending_rate'].'%',
                'icon' => 'heroicon-o-check-circle',
                'tone' => 'green',
            ],
            [
                'label' => 'Pending RSVP',
                'value' => number_format($stats['pending_rsvp']),
                'description' => $stats['rsvp_rate'].'% responded',
                'icon' => 'heroicon-o-clock',
                'tone' => 'amber',
            ],
            [
                'label' => 'Cards Generated',
                'value' => number_format($stats['generated_cards']),
                'description' => $stats['cards_rate'].'%',
                'icon' => 'heroicon-o-squares-2x2',
                'tone' => 'purple',
            ],
            [
                'label' => 'Guests Checked In',
                'value' => number_format($stats['guests_checked_in']),
                'description' => $stats['check_in_rate'].'% of expected guests',
                'icon' => 'heroicon-o-check-badge',
                'tone' => 'sky',
            ],
            [
                'label' => 'Messages Sent',
                'value' => number_format($stats['messages_sent']),
                'description' => $stats['messages_failed'] > 0
                    ? number_format($stats['messages_failed']).' failed'
                    : 'SMS & WhatsApp',
                'icon' => 'heroicon-o-paper-airplane',
                'tone' => $stats['messages_failed'] > 0
                    ? 'red'
                    : 'orange',
            ],
        ];
    }

    public function getWorkspaceQuickActions(): array
    {
        $stats = $this->getWorkspaceStats();

        return array_values(array_filter([
            [
                'title' => 'Invitees',
                'description' => 'Manage invitees',
                'icon' => 'heroicon-o-users',
                'url' => $this->getInviteesUrl(),
                'tone' => 'blue',
                'hint' => number_format($stats['invitees']).' invitees',
                'visible' => $this->canEditEvent(),
            ],
            [
                'title' => 'Card Types',
                'description' => 'Manage card types',
                'icon' => 'heroicon-o-rectangle-stack',
                'url' => $this->getCardTypesUrl(),
                'tone' => 'green',
                'visible' => $this->canEditEvent(),
            ],
            [
                'title' => 'Card Templates',
                'description' => 'Design templates',
                'icon' => 'heroicon-o-photo',
                'url' => $this->getCardTemplatesUrl(),
                'tone' => 'orange',
                'visible' => $this->canEditEvent(),
            ],
            [
                'title' => 'Generated Cards',
                'description' => 'Bulk card generation',
                'icon' => 'heroicon-o-printer',
                'url' => $this->getGeneratedCardsUrl(),
                'tone' => 'purple',
                'hint' => number_format($stats['generated_cards']).' ready',
                'visible' => $this->canEditEvent(),
            ],
            [
                'title' => 'Message Center',
                'description' => 'Send messages',
                'icon' => 'heroicon-o-envelope',
                'url' => $this->getMessageCenterUrl(),
                'tone' => 'sky',
                'visible' => $this->canSendEventMessages(),
            ],
            [
                'title' => 'RSVP Responses',
                'description' => 'View responses',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => $this->getInviteeResponsesUrl(),
                'tone' => 'green',
                'hint' => number_format($stats['pending_rsvp']).' pending',
                'visible' => true,
            ],
            [
                'title' => 'Invitee Uploads',
                'description' => 'Photos & wishes',
                'icon' => 'heroicon-o-cloud-arrow-up',
                'url' => $this->getInviteeUploadsUrl(),
                'tone' => 'sky',
                'visible' => $this->canEditEvent(),
            ],
            [
                'title' => 'Check-in Dashboard',
                'description' => 'Live attendance overview',
                'icon' => 'heroicon-o-chart-bar-square',
                'url' => $this->getCheckInDashboardUrl(),
                'tone' => 'green',
                'hint' => number_format($stats['guests_checked_in']).' guests admitted',
                'visible' => $this->canViewReports() || $this->canEditEvent(),
            ],
            [
                'title' => 'Gate Check-in',
                'description' => 'Scan & verify',
                'icon' => 'heroicon-o-qr-code',
                'url' => $this->getGateCheckInUrl(),
                'tone' => 'blue',
                'hint' => number_format($stats['check_in_transactions']).' transactions',
                'new_tab' => true,
                'visible' => method_exists($this->record, 'canBeCheckedInBy')
                    ? $this->record->canBeCheckedInBy(auth()->user())
                    : true,
            ],
            [
                'title' => 'Reports',
                'description' => 'View reports',
                'icon' => 'heroicon-o-chart-bar-square',
                'url' => $this->getReportsUrl(),
                'tone' => 'orange',
                'new_tab' => true,
                'visible' => $this->canViewReports(),
            ],
            [
                'title' => 'Edit Event',
                'description' => 'Update details',
                'icon' => 'heroicon-o-pencil-square',
                'url' => $this->getEditEventUrl(),
                'tone' => 'blue',
                'visible' => $this->canEditEvent(),
            ],
        ], fn (array $action): bool => (bool) ($action['visible'] ?? true)));
    }

    public function getInviteeDigitalPageSettings(): array
    {
        return [
            'Welcome Message' => filled($this->record->welcome_message),
            'Countdown' => (bool) ($this->record->show_countdown ?? false),
            'Photos Upload' => (bool) ($this->record->show_photo_upload ?? false),
            'Wishes' => (bool) ($this->record->show_wishes ?? false),
            'Organizer Contact' => (bool) ($this->record->show_organizer_contact ?? false),
        ];
    }

    public function exportEventSummary(): BinaryFileResponse
    {
        abort_unless(
            $this->canViewReports(),
            403,
            'You are not allowed to export this event report.',
        );

        $eventName = Str::slug($this->getEventName());

        return Excel::download(
            new EventSummaryExport((int) $this->record->getKey()),
            "{$eventName}-event-summary.xlsx",
        );
    }
}
