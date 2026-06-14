<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Services\ReminderSmsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReminderSms extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Reminder SMS';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reminder-sms';

    public ?array $data = [];

    public int $targetCount = 0;

    public string $previewMessage = 'Select an event to preview the reminder SMS.';

    public bool $missingTrackingColumns = false;

    public function mount(): void
    {
        $this->missingTrackingColumns = ! $this->hasRequiredInviteeSmsColumns();

        $this->form->fill([
            'sms_type' => SmsLog::TYPE_RSVP_PENDING_REMINDER,
            'target_group' => 'rsvp_pending',
        ]);

        $this->updatePreview();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Reminder SMS Setup')
                    ->description('Select the event, reminder type, and target invitees.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn () => Event::query()
                                ->latest()
                                ->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->updatePreview()),

                        Forms\Components\Select::make('sms_type')
                            ->label('Reminder Type')
                            ->options([
                                SmsLog::TYPE_INVITATION => 'Invitation SMS',
                                SmsLog::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
                                SmsLog::TYPE_ATTENDING_REMINDER => 'One Day Before Reminder',
                                SmsLog::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->updatePreview()),

                        Forms\Components\Select::make('target_group')
                            ->label('Target Invitees')
                            ->options([
                                'all' => 'All Invitees',
                                'rsvp_pending' => 'RSVP Pending Invitees',
                                'attending' => 'Attending Invitees',
                                'not_attending' => 'Not Attending Invitees',
                                'not_sent_invitation' => 'Invitation SMS Not Sent',
                                'failed_sms' => 'Failed SMS Only',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->updatePreview()),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Preview')
                    ->description('Check the estimated target count before sending.')
                    ->schema([
                        Forms\Components\Placeholder::make('target_count_preview')
                            ->label('Target Count')
                            ->content(fn () => $this->targetCount . ' invitee(s)'),

                        Forms\Components\Placeholder::make('preview_message_preview')
                            ->label('Sample Message')
                            ->content(fn () => $this->previewMessage),
                    ]),
            ])
            ->statePath('data');
    }

    public function updatePreview(): void
    {
        $this->missingTrackingColumns = ! $this->hasRequiredInviteeSmsColumns();

        if ($this->missingTrackingColumns) {
            $this->targetCount = 0;
            $this->previewMessage = 'Missing SMS tracking columns in invitees table. Run the migration first.';

            return;
        }

        $state = $this->form->getState();

        $eventId = $state['event_id'] ?? null;
        $smsType = $state['sms_type'] ?? SmsLog::TYPE_RSVP_PENDING_REMINDER;
        $targetGroup = $state['target_group'] ?? 'rsvp_pending';

        if (! $eventId) {
            $this->targetCount = 0;
            $this->previewMessage = 'Select an event to preview the reminder SMS.';

            return;
        }

        $invitees = $this->getTargetInvitees((int) $eventId, $targetGroup);

        $this->targetCount = $invitees->count();

        $sampleInvitee = $invitees->first();

        if (! $sampleInvitee) {
            $this->previewMessage = 'No invitees found for the selected target group.';

            return;
        }

        $this->previewMessage = $this->buildPreviewMessage($sampleInvitee, $smsType);
    }

    public function send(): void
    {
        $this->missingTrackingColumns = ! $this->hasRequiredInviteeSmsColumns();

        if ($this->missingTrackingColumns) {
            Notification::make()
                ->title('SMS tracking columns are missing')
                ->body('Run the invitees SMS tracking migration before sending reminders.')
                ->danger()
                ->send();

            return;
        }

        $state = $this->form->getState();

        $eventId = $state['event_id'] ?? null;
        $smsType = $state['sms_type'] ?? null;
        $targetGroup = $state['target_group'] ?? null;

        if (! $eventId || ! $smsType || ! $targetGroup) {
            Notification::make()
                ->title('Missing required information')
                ->body('Please select event, reminder type, and target group.')
                ->danger()
                ->send();

            return;
        }

        $invitees = $this->getTargetInvitees((int) $eventId, $targetGroup);

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No invitees found')
                ->body('There are no invitees matching the selected target group.')
                ->warning()
                ->send();

            return;
        }

        $service = app(ReminderSmsService::class);

        $result = match ($smsType) {
            SmsLog::TYPE_INVITATION => $service->sendBulkInvitationSms($invitees),
            SmsLog::TYPE_RSVP_PENDING_REMINDER => $service->sendBulkRsvpPendingReminders($invitees),
            SmsLog::TYPE_ATTENDING_REMINDER => $service->sendBulkAttendingReminders($invitees),
            SmsLog::TYPE_EVENT_DAY_REMINDER => $service->sendBulkEventDayReminders($invitees),
            default => [
                'sent' => 0,
                'failed' => 0,
                'skipped' => $invitees->count(),
            ],
        };

        $this->updatePreview();

        Notification::make()
            ->title('Reminder SMS completed')
            ->body("Sent: {$result['sent']} | Failed: {$result['failed']} | Skipped: {$result['skipped']}")
            ->success()
            ->send();
    }

    protected function getTargetInvitees(int $eventId, string $targetGroup): Collection
    {
        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $eventId)
            ->when($targetGroup === 'rsvp_pending', function ($query) {
                $query->where('rsvp_status', Invitee::RSVP_STATUS_PENDING);
            })
            ->when($targetGroup === 'attending', function ($query) {
                $query->where('rsvp_status', Invitee::RSVP_STATUS_ATTENDING);
            })
            ->when($targetGroup === 'not_attending', function ($query) {
                $query->where('rsvp_status', Invitee::RSVP_STATUS_NOT_ATTENDING);
            })
            ->when($targetGroup === 'not_sent_invitation', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereNull('invitation_sms_status')
                        ->orWhere('invitation_sms_status', '!=', Invitee::SMS_STATUS_SENT);
                });
            })
            ->when($targetGroup === 'failed_sms', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where('sms_status', Invitee::SMS_STATUS_FAILED)
                        ->orWhere('invitation_sms_status', Invitee::SMS_STATUS_FAILED)
                        ->orWhere('reminder_sms_status', Invitee::SMS_STATUS_FAILED)
                        ->orWhere('final_sms_status', Invitee::SMS_STATUS_FAILED);
                });
            })
            ->orderBy('name')
            ->get();
    }

    protected function buildPreviewMessage(Invitee $invitee, string $smsType): string
    {
        $event = $invitee->event;

        $eventName = $event?->title ?? 'the event';

        $eventDate = $event?->event_date
            ? $event->event_date->format('d M Y')
            : 'the event date';

        $venue = $event?->venue_name
            ?: $event?->venue_address
            ?: 'the venue';

        $serialNumber = $invitee->serial_number ?? 'N/A';
        $guestCount = $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1;

        $templates = [
            SmsLog::TYPE_INVITATION => 'Dear {name}, you are invited to {event_name} on {event_date} at {venue}. Serial: {serial_number}. Guests: {guest_count}. Please confirm attendance.',

            SmsLog::TYPE_RSVP_PENDING_REMINDER => 'Dear {name}, reminder to confirm your attendance for {event_name}. Serial: {serial_number}. Please RSVP as soon as possible.',

            SmsLog::TYPE_ATTENDING_REMINDER => 'Dear {name}, reminder: {event_name} is tomorrow at {venue}. Serial: {serial_number}. Please come with your invitation card.',

            SmsLog::TYPE_EVENT_DAY_REMINDER => 'Dear {name}, {event_name} is today at {venue}. Serial: {serial_number}. Please present your QR card at the gate.',
        ];

        return str_replace(
            [
                '{name}',
                '{event_name}',
                '{event_date}',
                '{venue}',
                '{serial_number}',
                '{guest_count}',
            ],
            [
                $invitee->name,
                $eventName,
                $eventDate,
                $venue,
                $serialNumber,
                $guestCount,
            ],
            $templates[$smsType] ?? $templates[SmsLog::TYPE_RSVP_PENDING_REMINDER]
        );
    }

    protected function hasRequiredInviteeSmsColumns(): bool
    {
        return Schema::hasColumn('invitees', 'invitation_sms_status')
            && Schema::hasColumn('invitees', 'invitation_sms_sent_at')
            && Schema::hasColumn('invitees', 'reminder_sms_status')
            && Schema::hasColumn('invitees', 'reminder_sms_sent_at')
            && Schema::hasColumn('invitees', 'final_sms_status')
            && Schema::hasColumn('invitees', 'final_sms_sent_at')
            && Schema::hasColumn('invitees', 'last_sms_error');
    }
}
