<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\Invitee;
use App\Services\InviteeConversationService;
use App\Services\InviteeInvitationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InviteeResponseTracker extends Page
{
    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.invitee-response-tracker';

    public Event $record;

    public string $search = '';

    public string $rsvpFilter = '';

    public string $openFilter = '';

    public string $channelFilter = '';

    public string $messageStatusFilter = '';

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

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
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

            Actions\Action::make('export')
                ->label('Export Responses')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportResponses()),

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
            ->when($this->rsvpFilter !== '', function ($query): void {
                match ($this->rsvpFilter) {
                    'pending' => $query->where(function ($query): void {
                        $query->whereNull('rsvp_status')
                            ->orWhere('rsvp_status', 'pending');
                    }),

                    'attending' => $query->where('rsvp_status', 'attending'),

                    'not_attending' => $query->whereIn('rsvp_status', [
                        'not_attending',
                        'declined',
                    ]),

                    'maybe' => $query->where('rsvp_status', 'maybe'),

                    default => null,
                };
            })
            ->when($this->openFilter !== '', function ($query): void {
                match ($this->openFilter) {
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
            ->when($this->messageStatusFilter !== '', function ($query): void {
                match ($this->messageStatusFilter) {
                    'not_sent' => $query->where(function ($query): void {
                        $query->where(function ($query): void {
                            $query->whereNull('last_message_status')
                                ->orWhere('last_message_status', 'not_sent');
                        })
                            ->where(function ($query): void {
                                $query->whereNull('sms_status')
                                    ->orWhere('sms_status', 'not_sent');
                            })
                            ->where(function ($query): void {
                                $query->whereNull('invitation_sms_status')
                                    ->orWhere('invitation_sms_status', 'not_sent');
                            });
                    }),

                    'queued' => $query->where(function ($query): void {
                        $query->whereIn('last_message_status', [
                            'pending',
                            'queued',
                        ])
                            ->orWhereIn('sms_status', [
                                'pending',
                                'queued',
                            ])
                            ->orWhereIn('invitation_sms_status', [
                                'pending',
                                'queued',
                            ]);
                    }),

                    'sending' => $query->where(function ($query): void {
                        $query->whereIn('last_message_status', [
                            'processing',
                            'sending',
                        ])
                            ->orWhereIn('sms_status', [
                                'processing',
                                'sending',
                            ])
                            ->orWhereIn('invitation_sms_status', [
                                'processing',
                                'sending',
                            ]);
                    }),

                    'sent' => $query->where(function ($query): void {
                        $query->whereIn('last_message_status', [
                            'accepted',
                            'submitted',
                            'sent',
                            'success',
                        ])
                            ->orWhereIn('sms_status', [
                                'accepted',
                                'submitted',
                                'sent',
                                'success',
                            ])
                            ->orWhereIn('invitation_sms_status', [
                                'accepted',
                                'submitted',
                                'sent',
                                'success',
                            ]);
                    }),

                    'delivered' => $query->where(function ($query): void {
                        $query->where('last_message_status', 'delivered')
                            ->orWhere('sms_status', 'delivered')
                            ->orWhere('invitation_sms_status', 'delivered');
                    }),

                    'read' => $query->where('last_message_status', 'read'),

                    'replied' => $query->where(function ($query): void {
                        $query->where('last_message_status', 'replied')
                            ->orWhereNotNull('last_reply_message');
                    }),

                    'failed' => $query->where(function ($query): void {
                        $query->whereIn('last_message_status', [
                            'failed',
                            'error',
                            'rejected',
                            'undelivered',
                            'expired',
                        ])
                            ->orWhereIn('sms_status', [
                                'failed',
                                'error',
                                'rejected',
                                'undelivered',
                                'expired',
                            ])
                            ->orWhereIn('invitation_sms_status', [
                                'failed',
                                'error',
                                'rejected',
                                'undelivered',
                                'expired',
                            ]);
                    }),

                    default => null,
                };
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
        $this->resendInvitation($inviteeId, 'sms');
    }

    public function resendWhatsAppInvitation(int $inviteeId): void
    {
        $this->resendInvitation($inviteeId, 'whatsapp');
    }

    public function canResendInvitations(): bool
    {
        $user = auth()->user();

        return $user && $user->canSendMessages() && $user->canAccessEvent($this->record);
    }

    protected function resendInvitation(int $inviteeId, string $channel): void
    {
        abort_unless($this->canResendInvitations(), 403);

        $invitee = $this->findInviteeForCurrentEvent($inviteeId);
        $result = app(InviteeInvitationService::class)->sendImmediately(
            $this->record,
            $invitee,
            $channel,
            'Manual resend from the Invitee Response Tracker.',
        );

        $label = $channel === 'whatsapp' ? 'WhatsApp' : 'SMS';
        $notification = Notification::make()
            ->title($label.' resend to '.$invitee->name)
            ->body($result['message']);

        match ($result['status']) {
            'submitted' => $notification->success(),
            'logged', 'skipped' => $notification->warning(),
            default => $notification->danger(),
        };

        $notification->send();
    }

    public function exportResponses(): StreamedResponse
    {
        $fileName = 'invitee-responses-'
            .str($this->record->name ?? $this->record->title ?? 'event')
                ->slug()
            .'-'
            .now()->format('Y-m-d-His')
            .'.csv';

        $invitees = $this->invitees;

        return response()->streamDownload(
            function () use ($invitees): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    throw new \RuntimeException('Unable to open export stream.');
                }

                fputcsv($handle, [
                    'Guest Name',
                    'Phone',
                    'SMS Status',
                    'WhatsApp Status',
                    'Opened',
                    'Views',
                    'Last Opened',
                    'RSVP Status',
                    'Confirmed Guests',
                    'Allowed Guests',
                    'RSVP Date',
                    'Comment',
                    'Serial Number',
                    'Short Code',
                ]);

                foreach ($invitees as $invitee) {
                    fputcsv($handle, [
                        $invitee->name,
                        $invitee->phone,
                        $invitee->sms_status
                            ?? $invitee->invitation_sms_status
                            ?? 'not_sent',
                        $invitee->last_message_channel === 'whatsapp'
                            ? ($invitee->last_message_status ?? 'sent')
                            : 'not_sent',
                        filled($invitee->first_opened_at) ? 'Yes' : 'No',
                        (int) ($invitee->open_count ?? 0),
                        optional($invitee->last_opened_at)?->format('Y-m-d H:i:s'),
                        $invitee->rsvp_status ?: 'pending',
                        (int) ($invitee->confirmed_guests ?? 0),
                        (int) ($invitee->allowed_guests ?? 1),
                        optional($invitee->rsvp_confirmed_at)?->format('Y-m-d H:i:s'),
                        $invitee->last_reply_message,
                        $invitee->serial_number,
                        $invitee->short_code,
                    ]);
                }

                fclose($handle);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    protected function findInviteeForCurrentEvent(int $inviteeId): Invitee
    {
        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $this->record->id)
            ->findOrFail($inviteeId);
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
        $this->openFilter = 'opened';
        $this->dispatch('$refresh');
    }

    public function filterNotOpened(): void
    {
        $this->openFilter = 'not_opened';
        $this->dispatch('$refresh');
    }

    public function filterRecentOpens(): void
    {
        $this->openFilter = 'recent_opens';
        $this->dispatch('$refresh');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->rsvpFilter = '';
        $this->openFilter = '';
        $this->channelFilter = '';
        $this->messageStatusFilter = '';
        $this->dispatch('$refresh');
    }
}
