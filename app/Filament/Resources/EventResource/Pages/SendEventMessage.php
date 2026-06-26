<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Jobs\GenerateInviteeCardJob;
use App\Jobs\SendInvitationSmsJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class SendEventMessage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.send-event-message';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Message Center';
    }

    public function getHeading(): string
    {
        return 'Message Center';
    }

    public function getSubheading(): ?string
    {
        return 'Send invitations, generate missing cards, monitor SMS delivery, and prepare WhatsApp communication for this event.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEvent')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('view', [
                    'record' => $this->record,
                ])),

            Action::make('openInvitees')
                ->label('Open Invitees')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(fn (): string => EventResource::getUrl('view', [
                    'record' => $this->record,
                ])),

            Action::make('generateMissingCards')
                ->label('Generate Missing Cards')
                ->icon('heroicon-o-qr-code')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generate Missing Cards')
                ->modalDescription('This will queue card generation only for invitees who do not already have generated cards.')
                ->modalSubmitActionLabel('Generate Cards')
                ->disabled(fn (): bool => $this->missingCardsCount === 0)
                ->action(fn () => $this->generateMissingCards()),

            Action::make('sendSmsInvitations')
                ->label('Send SMS Invitations')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send SMS Invitations')
                ->modalDescription('This will queue SMS invitations only for invitees with phone number, private link, and generated card. Already queued or sent invitations will be skipped.')
                ->modalSubmitActionLabel('Queue SMS')
                ->disabled(fn (): bool => $this->unsentEligibleSmsInviteesCount === 0)
                ->action(fn () => $this->sendSmsInvitations()),
        ];
    }

    public function generateMissingCards(): void
    {
        $invitees = $this->record
            ->invitees()
            ->whereDoesntHave('generatedCards', function ($query): void {
                $query->where('status', 'generated');
            })
            ->get();

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No missing cards')
                ->body('All invitees already have generated cards.')
                ->warning()
                ->send();

            return;
        }

        foreach ($invitees as $invitee) {
            GenerateInviteeCardJob::dispatch($invitee->id);
        }

        Notification::make()
            ->title('Card generation queued')
            ->body($invitees->count() . ' invitee card(s) have been queued for generation.')
            ->success()
            ->send();
    }

    public function sendSmsInvitations(): void
    {
        $invitees = $this->record
            ->invitees()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '')
            ->whereHas('generatedCards', function ($query): void {
                $query->where('status', 'generated');
            })
            ->get();

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No eligible invitees')
                ->body('No invitees have phone number, private link, and generated card.')
                ->danger()
                ->send();

            return;
        }

        $queued = 0;
        $skipped = 0;

        DB::transaction(function () use ($invitees, &$queued, &$skipped): void {
            foreach ($invitees as $invitee) {
                $alreadySentOrQueued = false;

                if (method_exists($invitee, 'smsLogs')) {
                    $alreadySentOrQueued = $invitee
                        ->smsLogs()
                        ->where('event_id', $this->record->id)
                        ->where('sms_type', 'invitation')
                        ->whereIn('status', [
                            'queued',
                            'pending',
                            'sending',
                            'sent',
                        ])
                        ->exists();
                }

                if ($alreadySentOrQueued) {
                    $skipped++;

                    continue;
                }

                SendInvitationSmsJob::dispatch(
                    eventId: $this->record->id,
                    inviteeId: $invitee->id,
                );

                $queued++;
            }
        });

        if ($queued === 0) {
            Notification::make()
                ->title('No new SMS queued')
                ->body($skipped . ' invitee(s) were skipped because invitation SMS was already queued or sent.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('SMS invitations queued')
            ->body($queued . ' SMS invitation(s) queued. ' . $skipped . ' skipped because they were already queued or sent.')
            ->success()
            ->send();
    }

    public function sendWhatsappInvitations(): void
    {
        Notification::make()
            ->title('WhatsApp coming next')
            ->body('This button will send WhatsApp invitation links from the Message Center.')
            ->info()
            ->send();
    }

    public function sendRsvpReminderSms(): void
    {
        Notification::make()
            ->title('RSVP reminder coming next')
            ->body('This button will send SMS reminders to invitees with pending RSVP.')
            ->info()
            ->send();
    }

    public function sendEventDayReminderSms(): void
    {
        Notification::make()
            ->title('Event day reminder coming next')
            ->body('This button will send final SMS reminders to confirmed guests on the event day.')
            ->info()
            ->send();
    }

    public function sendThankYouSms(): void
    {
        Notification::make()
            ->title('Thank you SMS coming next')
            ->body('This button will send thank-you SMS after the event.')
            ->info()
            ->send();
    }

    public function getInviteesCountProperty(): int
    {
        return $this->record->invitees()->count();
    }

    public function getGeneratedCardsCountProperty(): int
    {
        return $this->record
            ->generatedCards()
            ->where('status', 'generated')
            ->count();
    }

    public function getMissingCardsCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->whereDoesntHave('generatedCards', function ($query): void {
                $query->where('status', 'generated');
            })
            ->count();
    }

    public function getEligibleSmsInviteesCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '')
            ->whereHas('generatedCards', function ($query): void {
                $query->where('status', 'generated');
            })
            ->count();
    }

    public function getUnsentEligibleSmsInviteesCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '')
            ->whereHas('generatedCards', function ($query): void {
                $query->where('status', 'generated');
            })
            ->whereDoesntHave('smsLogs', function ($query): void {
                $query
                    ->where('event_id', $this->record->id)
                    ->where('sms_type', 'invitation')
                    ->whereIn('status', [
                        'queued',
                        'pending',
                        'sending',
                        'sent',
                    ]);
            })
            ->count();
    }

    public function getAttendingCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->where('rsvp_status', 'attending')
            ->count();
    }

    public function getPendingRsvpCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->where(function ($query): void {
                $query
                    ->whereNull('rsvp_status')
                    ->orWhere('rsvp_status', '')
                    ->orWhere('rsvp_status', 'pending');
            })
            ->count();
    }

    public function getNotAttendingCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->where('rsvp_status', 'not_attending')
            ->count();
    }

    public function getSentSmsCountProperty(): int
    {
        if (! method_exists($this->record, 'smsLogs')) {
            return 0;
        }

        return $this->record
            ->smsLogs()
            ->where('status', 'sent')
            ->count();
    }

    public function getQueuedSmsCountProperty(): int
    {
        if (! method_exists($this->record, 'smsLogs')) {
            return 0;
        }

        return $this->record
            ->smsLogs()
            ->whereIn('status', [
                'queued',
                'pending',
                'sending',
            ])
            ->count();
    }

    public function getFailedSmsCountProperty(): int
    {
        if (! method_exists($this->record, 'smsLogs')) {
            return 0;
        }

        return $this->record
            ->smsLogs()
            ->where('status', 'failed')
            ->count();
    }

    public function getSentMessagesCountProperty(): int
    {
        if (! method_exists($this->record, 'messageLogs')) {
            return 0;
        }

        return $this->record
            ->messageLogs()
            ->whereIn('status', [
                'sent',
                'accepted',
                'delivered',
                'read',
            ])
            ->count();
    }

    public function getFailedMessagesCountProperty(): int
    {
        if (! method_exists($this->record, 'messageLogs')) {
            return 0;
        }

        return $this->record
            ->messageLogs()
            ->where('status', 'failed')
            ->count();
    }

    public function getEventNameProperty(): string
    {
        return $this->record->title ?? $this->record->name ?? 'Event';
    }

    public function getEventDateProperty(): string
    {
        return $this->record->event_date
            ? $this->record->event_date->format('d M Y')
            : '-';
    }

    public function getEventVenueProperty(): string
    {
        return $this->record->venue_name
            ?? $this->record->venue
            ?? $this->record->venue_address
            ?? '-';
    }
}