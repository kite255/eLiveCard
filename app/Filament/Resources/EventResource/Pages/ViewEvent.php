<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Exports\EventSummaryExport;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Widgets\EventQuickActions;
use App\Filament\Resources\EventResource\Widgets\EventWorkspaceStats;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            EventWorkspaceStats::class,
            EventQuickActions::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Event')
                ->icon('heroicon-o-pencil-square')
                ->color('gray'),

            Actions\Action::make('exportEventSummary')
                ->label('Export Event Summary')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $eventName = Str::slug((string) ($this->record->title ?? $this->record->name ?? 'event-' . $this->record->id));

                    return Excel::download(
                        new EventSummaryExport((int) $this->record->id),
                        $eventName . '-event-summary.xlsx'
                    );
                }),

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

            Actions\Action::make('deliveryLogs')
                ->label('Delivery Logs')
                ->icon('heroicon-o-inbox-stack')
                ->color('info')
                ->url(fn () => EventResource::getUrl('view', [
                    'record' => $this->record,
                ])),

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
