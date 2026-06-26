<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Widgets\EventWorkspaceStats;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            EventWorkspaceStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Event')
                ->icon('heroicon-o-pencil-square')
                ->color('gray'),

            Actions\Action::make('messageCenter')
                ->label('Message Center')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->url(fn () => EventResource::getUrl('send-message', [
                    'record' => $this->record,
                ])),

            Actions\Action::make('inviteeResponses')
                ->label('Invitee Responses')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning')
                ->url(fn () => EventResource::getUrl('invitee-responses', [
                    'record' => $this->record,
                ])),

            Actions\Action::make('gateCheckIn')
                ->label('Gate Check-in')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->url(fn () => route('gate.check-in.show', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('viewReports')
                ->label('Reports')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(fn () => url('/admin/reports?event_id=' . $this->record->id))
                ->openUrlInNewTab(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'Event Workspace: ' . ($this->record->title ?? $this->record->name ?? 'Event');
    }

    public function getSubheading(): ?string
    {
        return 'Manage invitations, invitees, card templates, SMS, WhatsApp, RSVP, responses, replies, and gate check-ins for this event.';
    }
}