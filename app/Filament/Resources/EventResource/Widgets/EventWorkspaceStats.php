<?php

namespace App\Filament\Resources\EventResource\Widgets;

use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventWorkspaceStats extends StatsOverviewWidget
{
    public ?Event $record = null;

    protected function getStats(): array
    {
        $event = $this->record;

        if (! $event) {
            return [];
        }

        return [
            Stat::make('Invitees', number_format($event->invitees_count))
                ->description('Total invited guests')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Generated Cards', number_format($event->generated_cards_count))
                ->description('Personalized cards')
                ->descriptionIcon('heroicon-m-identification')
                ->color('warning'),

            Stat::make('RSVP Attending', number_format($event->rsvp_attending_count))
                ->description('Confirmed attendance')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Checked In', number_format($event->check_ins_count))
                ->description('Gate check-ins')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('info'),

            Stat::make('SMS Sent', number_format($event->sms_sent_count))
                ->description('Successful SMS')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('success'),

            Stat::make('WhatsApp Sent', number_format($event->whatsapp_sent_count))
                ->description('Successful WhatsApp')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),

            Stat::make('Failed Messages', number_format($event->communication_failed_count))
                ->description('SMS or WhatsApp failures')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($event->communication_failed_count > 0 ? 'danger' : 'gray'),

            Stat::make('Event Status', ucfirst($event->status ?? 'Draft'))
                ->description($event->event_date_display . ' • ' . $event->time_display)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color(match ($event->status) {
                    Event::STATUS_ACTIVE => 'success',
                    Event::STATUS_COMPLETED => 'info',
                    Event::STATUS_CANCELLED => 'danger',
                    default => 'gray',
                }),
        ];
    }
}