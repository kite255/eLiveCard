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
        return 'Send Message';
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
                ->modalSubmitActionLabel('Generate')
                ->action(fn () => $this->generateMissingCards()),

            Action::make('sendSmsInvitations')
                ->label('Send SMS Invitations')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send SMS Invitations')
                ->modalDescription('This will send SMS invitations only to invitees with phone number, private link, and generated card.')
                ->modalSubmitActionLabel('Send SMS')
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
                $alreadySent = false;

                if (method_exists($invitee, 'smsLogs')) {
                    $alreadySent = $invitee
                        ->smsLogs()
                        ->where('event_id', $this->record->id)
                        ->where('sms_type', 'invitation')
                        ->whereIn('status', ['queued', 'pending', 'sent'])
                        ->exists();
                }

                if ($alreadySent) {
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

        Notification::make()
            ->title('SMS invitations queued')
            ->body($queued . ' SMS invitation(s) queued. ' . $skipped . ' skipped because they were already queued or sent.')
            ->success()
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

    public function getMissingCardsCountProperty(): int
    {
        return $this->record
            ->invitees()
            ->whereDoesntHave('generatedCards', function ($query): void {
                $query->where('status', 'generated');
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
            ->where('rsvp_status', 'pending')
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
            ->whereIn('status', ['sent', 'accepted', 'delivered', 'read'])
            ->count();
    }
}