<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\Invitee;
use App\Services\InviteeConversationService;
use App\Services\SmsService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class InviteeResponseTracker extends Page
{
    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.invitee-response-tracker';

    public Event $record;

    public string $search = '';

    public string $statusFilter = '';

    public string $channelFilter = '';

    public array $whatsappMessages = [];

    public array $smsMessages = [];

    public array $rsvpStatuses = [];

    public array $confirmedGuests = [];

    public function mount(Event | int | string $record): void
    {
        $this->record = $record instanceof Event
            ? $record
            : Event::query()->findOrFail($record);

        $this->loadInviteeFormDefaults();
    }

    public function getTitle(): string
    {
        return 'Invitee Response Tracker';
    }

    public function getSubheading(): ?string
    {
        return $this->record->name
            ?? $this->record->title
            ?? 'Track delivery, opens, RSVP and invitee replies';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_event')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('view', [
                    'record' => $this->record,
                ])),

            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function (): void {
                    $this->loadInviteeFormDefaults();
                    $this->dispatch('$refresh');

                    Notification::make()
                        ->title('Tracker refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function loadInviteeFormDefaults(): void
    {
        Invitee::query()
            ->where('event_id', $this->record->id)
            ->select(['id', 'rsvp_status', 'confirmed_guests'])
            ->get()
            ->each(function (Invitee $invitee): void {
                $this->whatsappMessages[$invitee->id] ??= '';
                $this->smsMessages[$invitee->id] ??= '';
                $this->rsvpStatuses[$invitee->id] = $invitee->rsvp_status ?: 'pending';
                $this->confirmedGuests[$invitee->id] = (int) ($invitee->confirmed_guests ?? 0);
            });
    }

    public function getInviteesProperty(): Collection
    {
        return Invitee::query()
            ->with([
                'event',
                'cardType',
                'conversations' => fn ($query) => $query->latest()->limit(5),
            ])
            ->where('event_id', $this->record->id)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%')
                        ->orWhere('serial_number', 'like', '%' . $this->search . '%')
                        ->orWhere('short_code', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== '', function ($query): void {
                match ($this->statusFilter) {
                    'rsvp_pending' => $query->where(function ($query): void {
                        $query->whereNull('rsvp_status')
                            ->orWhere('rsvp_status', 'pending');
                    }),

                    'attending' => $query->where('rsvp_status', 'attending'),

                    'not_attending' => $query->where('rsvp_status', 'not_attending'),

                    'maybe' => $query->where('rsvp_status', 'maybe'),

                    'replied' => $query->where(function ($query): void {
                        $query->where('last_message_status', 'replied')
                            ->orWhereNotNull('last_reply_message');
                    }),

                    'failed' => $query->where(function ($query): void {
                        $query->where('last_message_status', 'failed')
                            ->orWhere('sms_status', 'failed')
                            ->orWhere('invitation_sms_status', 'failed');
                    }),

                    'not_sent' => $query->where(function ($query): void {
                        $query->whereNull('last_message_channel')
                            ->orWhere('last_message_status', 'not_sent');
                    }),

                    'opened' => $query->whereNotNull('first_opened_at'),

                    'not_opened' => $query->whereNull('first_opened_at'),

                    'recent_opens' => $query
                        ->whereNotNull('last_opened_at')
                        ->where('last_opened_at', '>=', now()->subDay()),

                    default => null,
                };
            })
            ->when($this->channelFilter !== '', function ($query): void {
                $query->where('last_message_channel', $this->channelFilter);
            })
            ->latest()
            ->get();
    }

    public function getStatsProperty(): array
    {
        $baseQuery = Invitee::query()
            ->where('event_id', $this->record->id);

        $total = (clone $baseQuery)->count();

        $smsSent = (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereNotNull('last_sms_sent_at')
                    ->orWhereNotNull('sms_sent_at')
                    ->orWhere('sms_status', 'sent')
                    ->orWhere('invitation_sms_status', 'sent');
            })
            ->count();

        $whatsappSent = (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereNotNull('last_whatsapp_sent_at')
                    ->orWhereNotNull('whatsapp_message_id')
                    ->orWhere('last_message_channel', 'whatsapp');
            })
            ->count();

        $sent = (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereNotNull('last_sms_sent_at')
                    ->orWhereNotNull('sms_sent_at')
                    ->orWhere('sms_status', 'sent')
                    ->orWhere('invitation_sms_status', 'sent')
                    ->orWhereNotNull('last_whatsapp_sent_at')
                    ->orWhereNotNull('whatsapp_message_id')
                    ->orWhere('last_message_status', 'sent')
                    ->orWhere('last_message_status', 'delivered')
                    ->orWhere('last_message_status', 'read');
            })
            ->count();

        $opened = (clone $baseQuery)
            ->whereNotNull('first_opened_at')
            ->count();

        $notOpened = max($total - $opened, 0);

        $recentOpens = (clone $baseQuery)
            ->whereNotNull('last_opened_at')
            ->where('last_opened_at', '>=', now()->subDay())
            ->count();

        $attending = (clone $baseQuery)
            ->where('rsvp_status', 'attending')
            ->count();

        $notAttending = (clone $baseQuery)
            ->where('rsvp_status', 'not_attending')
            ->count();

        $pending = (clone $baseQuery)
            ->where(function ($query): void {
                $query->whereNull('rsvp_status')
                    ->orWhere('rsvp_status', 'pending');
            })
            ->count();

        $failed = (clone $baseQuery)
            ->where(function ($query): void {
                $query->where('last_message_status', 'failed')
                    ->orWhere('sms_status', 'failed')
                    ->orWhere('invitation_sms_status', 'failed');
            })
            ->count();

        $replied = (clone $baseQuery)
            ->where(function ($query): void {
                $query->where('last_message_status', 'replied')
                    ->orWhereNotNull('last_reply_message');
            })
            ->count();

        return [
            'total' => $total,

            'sent' => $sent,
            'sms_sent' => $smsSent,
            'whatsapp_sent' => $whatsappSent,

            'opened' => $opened,
            'not_opened' => $notOpened,
            'recent_opens' => $recentOpens,
            'open_rate' => $total > 0 ? round(($opened / $total) * 100) : 0,

            'replied' => $replied,
            'attending' => $attending,
            'not_attending' => $notAttending,
            'pending' => $pending,
            'failed' => $failed,
            'response_rate' => $total > 0 ? round((($attending + $notAttending) / $total) * 100) : 0,
        ];
    }

    public function replyWhatsApp(int $inviteeId): void
    {
        $message = trim((string) ($this->whatsappMessages[$inviteeId] ?? ''));

        if ($message === '') {
            Notification::make()
                ->title('Message is required')
                ->body('Please write a WhatsApp reply before saving.')
                ->danger()
                ->send();

            return;
        }

        $invitee = $this->findInviteeForCurrentEvent($inviteeId);

        app(InviteeConversationService::class)
            ->saveOutgoingWhatsAppReply(
                invitee: $invitee,
                message: $message,
                status: 'sent'
            );

        $this->whatsappMessages[$inviteeId] = '';
        $this->dispatch('$refresh');

        Notification::make()
            ->title('WhatsApp reply saved')
            ->body('The reply has been recorded. Real WhatsApp API sending can be connected next.')
            ->success()
            ->send();
    }

    public function replySms(int $inviteeId): void
    {
        $message = trim((string) ($this->smsMessages[$inviteeId] ?? ''));

        if ($message === '') {
            Notification::make()
                ->title('Message is required')
                ->body('Please write an SMS reply before saving.')
                ->danger()
                ->send();

            return;
        }

        $invitee = $this->findInviteeForCurrentEvent($inviteeId);

        app(InviteeConversationService::class)
            ->saveOutgoingSmsReply(
                invitee: $invitee,
                message: $message,
                status: 'sent'
            );

        $this->smsMessages[$inviteeId] = '';
        $this->dispatch('$refresh');

        Notification::make()
            ->title('SMS reply saved')
            ->body('The reply has been recorded. Real SMS API sending can be connected next.')
            ->success()
            ->send();
    }

    public function resendSmsInvitation(int $inviteeId): void
    {
        $invitee = $this->findInviteeForCurrentEvent($inviteeId);

        if (blank($invitee->phone)) {
            Notification::make()
                ->title('SMS not sent')
                ->body('This invitee does not have a phone number.')
                ->danger()
                ->send();

            return;
        }

        $messageType = $this->resendMessageTypeForSms($invitee);
        $message = $this->buildResendMessage($invitee, $messageType);
        $reference = $messageType . '_' . $invitee->id . '_' . now()->format('YmdHis');

        try {
            $providerResponse = app(SmsService::class)->send(
                $invitee->phone,
                $message,
                $reference
            );

            app(InviteeConversationService::class)
                ->saveOutgoingSmsReply(
                    invitee: $invitee,
                    message: $message,
                    status: 'sent'
                );

            $invitee->forceFill([
                'last_sms_sent_at' => now(),
                'sms_sent_at' => $invitee->sms_sent_at ?? now(),
                'sms_status' => 'sent',
                'sms_error' => null,
                'last_sms_error' => null,
                'invitation_sms_status' => $messageType === 'invitation_card'
                    ? 'sent'
                    : ($invitee->invitation_sms_status ?? 'sent'),
                'invitation_sms_sent_at' => $messageType === 'invitation_card'
                    ? now()
                    : ($invitee->invitation_sms_sent_at ?? null),
                'last_message_channel' => 'sms',
                'last_message_status' => 'sent',
                'last_message_error' => null,
            ])->save();

            $this->loadInviteeFormDefaults();
            $this->dispatch('$refresh');

            Notification::make()
                ->title($messageType === 'rsvp_reminder' ? 'Reminder SMS sent' : 'Invitation SMS sent')
                ->body('SMS sent to ' . $invitee->name . '.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            $invitee->forceFill([
                'sms_status' => 'failed',
                'last_message_channel' => 'sms',
                'last_message_status' => 'failed',
                'sms_error' => $exception->getMessage(),
                'last_sms_error' => $exception->getMessage(),
                'last_message_error' => $exception->getMessage(),
            ])->save();

            $this->dispatch('$refresh');

            Notification::make()
                ->title('SMS failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resendWhatsAppInvitation(int $inviteeId): void
    {
        $invitee = $this->findInviteeForCurrentEvent($inviteeId);

        if (blank($invitee->phone)) {
            Notification::make()
                ->title('WhatsApp not sent')
                ->body('This invitee does not have a phone number.')
                ->danger()
                ->send();

            return;
        }

        $messageType = $this->resendMessageTypeForWhatsApp($invitee);
        $message = $this->buildResendMessage($invitee, $messageType);

        try {
            /*
             |--------------------------------------------------------------------------
             | WhatsApp sending
             |--------------------------------------------------------------------------
             |
             | This tracker safely records the WhatsApp resend action. If you already
             | have a real WhatsApp sending service/job in the project, connect it here.
             | The notification still responds immediately, so the button never appears dead.
             |
             */

            app(InviteeConversationService::class)
                ->saveOutgoingWhatsAppReply(
                    invitee: $invitee,
                    message: $message,
                    status: 'sent'
                );

            $invitee->forceFill([
                'last_whatsapp_sent_at' => now(),
                'last_message_channel' => 'whatsapp',
                'last_message_status' => 'sent',
                'last_message_error' => null,
            ])->save();

            $this->loadInviteeFormDefaults();
            $this->dispatch('$refresh');

            Notification::make()
                ->title($messageType === 'rsvp_reminder' ? 'WhatsApp reminder recorded' : 'WhatsApp invitation recorded')
                ->body('The WhatsApp resend action was recorded for ' . $invitee->name . '.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            $invitee->forceFill([
                'last_message_channel' => 'whatsapp',
                'last_message_status' => 'failed',
                'last_message_error' => $exception->getMessage(),
            ])->save();

            $this->dispatch('$refresh');

            Notification::make()
                ->title('WhatsApp failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function findInviteeForCurrentEvent(int $inviteeId): Invitee
    {
        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $this->record->id)
            ->findOrFail($inviteeId);
    }

    protected function resendMessageTypeForSms(Invitee $invitee): string
    {
        $alreadySent = filled($invitee->last_sms_sent_at)
            || filled($invitee->sms_sent_at)
            || filled($invitee->invitation_sms_sent_at)
            || filled($invitee->sms_message_id)
            || in_array($invitee->sms_status, ['queued', 'sending', 'sent', 'delivered'], true)
            || in_array($invitee->invitation_sms_status, ['queued', 'sending', 'sent', 'delivered'], true);

        return $alreadySent ? 'rsvp_reminder' : 'invitation_card';
    }

    protected function resendMessageTypeForWhatsApp(Invitee $invitee): string
    {
        $alreadySent = filled($invitee->last_whatsapp_sent_at)
            || filled($invitee->whatsapp_message_id)
            || $invitee->last_message_channel === 'whatsapp';

        return $alreadySent ? 'rsvp_reminder' : 'invitation_card';
    }

    protected function buildResendMessage(Invitee $invitee, string $messageType): string
    {
        $event = $invitee->event ?? $this->record;
        $eventName = $event->name ?? $event->title ?? 'tukio';
        $privateLink = filled($invitee->short_code)
            ? url('/i/' . $invitee->short_code)
            : '';

        if ($messageType === 'rsvp_reminder') {
            return trim("Ndugu {$invitee->name}, tafadhali thibitisha kuhudhuria {$eventName}. Fungua kadi yako hapa: {$privateLink}");
        }

        return trim("Ndugu {$invitee->name}, umealikwa kwenye {$eventName}. Fungua kadi yako hapa: {$privateLink}");
    }

    public function updateRsvp(int $inviteeId): void
    {
        $invitee = $this->findInviteeForCurrentEvent($inviteeId);

        $status = $this->rsvpStatuses[$inviteeId] ?? 'pending';
        $confirmedGuests = (int) ($this->confirmedGuests[$inviteeId] ?? 0);
        $allowedGuests = (int) ($invitee->allowed_guests ?? 1);

        if ($confirmedGuests < 0) {
            $confirmedGuests = 0;
        }

        if ($confirmedGuests > $allowedGuests) {
            $confirmedGuests = $allowedGuests;
        }

        if ($status === 'not_attending') {
            $confirmedGuests = 0;
        }

        if ($status === 'attending' && $confirmedGuests < 1) {
            $confirmedGuests = 1;
        }

        $invitee->update([
            'rsvp_status' => $status,
            'confirmed_guests' => $confirmedGuests,
            'rsvp_confirmed_at' => now(),
        ]);

        $this->confirmedGuests[$inviteeId] = $confirmedGuests;
        $this->loadInviteeFormDefaults();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('RSVP updated')
            ->success()
            ->send();
    }

    public function filterOpened(): void
    {
        $this->statusFilter = 'opened';
        $this->dispatch('$refresh');
    }

    public function filterNotOpened(): void
    {
        $this->statusFilter = 'not_opened';
        $this->dispatch('$refresh');
    }

    public function filterRecentOpens(): void
    {
        $this->statusFilter = 'recent_opens';
        $this->dispatch('$refresh');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->channelFilter = '';
        $this->dispatch('$refresh');
    }
}
