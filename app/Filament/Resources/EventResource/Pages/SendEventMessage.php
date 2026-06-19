<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

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
        ];
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