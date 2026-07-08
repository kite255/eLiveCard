<?php

namespace App\Filament\Resources\EventResource\Widgets;

use App\Models\Event;
use App\Models\MessageLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventWorkspaceStats extends StatsOverviewWidget
{
    public ?Event $record = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $event = $this->record;

        if (! $event) {
            return [];
        }

        $eventId = $event->getKey();

        $inviteesCount = $this->safeCount($event, 'invitees');
        $generatedCardsCount = $this->safeCount($event, 'generatedCards');
        $checkInsCount = $this->safeCount($event, 'checkIns');

        $smsSentCount = $this->messageLogCount($eventId, 'sms', ['sent', 'delivered', 'read', 'accepted', 'logged']);
        $smsFailedCount = $this->messageLogCount($eventId, 'sms', ['failed', 'rejected']);

        $whatsappSentCount = $this->messageLogCount($eventId, 'whatsapp', ['sent', 'delivered', 'read', 'accepted', 'logged']);
        $whatsappFailedCount = $this->messageLogCount($eventId, 'whatsapp', ['failed', 'rejected']);

        $communicationFailedCount = $smsFailedCount + $whatsappFailedCount;

        $rsvpAttendingCount = $this->inviteeStatusCount($event, 'rsvp_status', ['attending', 'confirmed']);
        $rsvpNotAttendingCount = $this->inviteeStatusCount($event, 'rsvp_status', ['not_attending', 'declined']);
        $rsvpPendingCount = max($inviteesCount - ($rsvpAttendingCount + $rsvpNotAttendingCount), 0);

        $notCheckedInCount = max($inviteesCount - $checkInsCount, 0);

        $totalAllowedGuests = $this->inviteeSum($event, 'allowed_guests');
        $checkedInGuests = $this->checkInSum($event, 'guests_checked_in');

        return [
            Stat::make('Invitees', number_format($inviteesCount))
                ->description('Total invited guests')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Generated Cards', number_format($generatedCardsCount))
                ->description('Personalized invitation cards')
                ->descriptionIcon('heroicon-m-identification')
                ->color('warning'),

            Stat::make('RSVP Attending', number_format($rsvpAttendingCount))
                ->description(number_format($rsvpPendingCount) . ' pending responses')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Not Attending', number_format($rsvpNotAttendingCount))
                ->description('Declined invitation')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($rsvpNotAttendingCount > 0 ? 'danger' : 'gray'),

            Stat::make('Checked In', number_format($checkInsCount))
                ->description(number_format($notCheckedInCount) . ' not checked in')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('info'),

            Stat::make('Guest Capacity', number_format($totalAllowedGuests))
                ->description(number_format($checkedInGuests) . ' guests checked in')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('SMS Sent', number_format($smsSentCount))
                ->description(number_format($smsFailedCount) . ' failed SMS')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($smsFailedCount > 0 ? 'warning' : 'success'),

            Stat::make('WhatsApp Sent', number_format($whatsappSentCount))
                ->description(number_format($whatsappFailedCount) . ' failed WhatsApp')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color($whatsappFailedCount > 0 ? 'warning' : 'success'),

            Stat::make('Failed Messages', number_format($communicationFailedCount))
                ->description('SMS or WhatsApp failures')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($communicationFailedCount > 0 ? 'danger' : 'gray'),

            Stat::make('Event Status', ucfirst((string) ($event->status ?? 'draft')))
                ->description($this->eventScheduleDescription($event))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color(match ($event->status) {
                    Event::STATUS_ACTIVE => 'success',
                    Event::STATUS_COMPLETED => 'info',
                    Event::STATUS_CANCELLED => 'danger',
                    default => 'gray',
                }),
        ];
    }

    private function safeCount(Event $event, string $relationship): int
    {
        if (! method_exists($event, $relationship)) {
            return 0;
        }

        return (int) $event->{$relationship}()->count();
    }

    private function inviteeStatusCount(Event $event, string $column, array $statuses): int
    {
        if (! method_exists($event, 'invitees')) {
            return 0;
        }

        return (int) $event->invitees()
            ->whereIn($column, $statuses)
            ->count();
    }

    private function inviteeSum(Event $event, string $column): int
    {
        if (! method_exists($event, 'invitees')) {
            return 0;
        }

        return (int) $event->invitees()->sum($column);
    }

    private function checkInSum(Event $event, string $column): int
    {
        if (! method_exists($event, 'checkIns')) {
            return 0;
        }

        return (int) $event->checkIns()->sum($column);
    }

    private function messageLogCount(int|string $eventId, string $channel, array $statuses): int
    {
        return (int) MessageLog::query()
            ->where('event_id', $eventId)
            ->where('channel', $channel)
            ->whereIn('status', $statuses)
            ->count();
    }

    private function eventScheduleDescription(Event $event): string
    {
        $date = $event->event_date_display ?? null;
        $time = $event->time_display ?? null;

        if ($date && $time) {
            return $date . ' • ' . $time;
        }

        if ($date) {
            return (string) $date;
        }

        if ($time) {
            return (string) $time;
        }

        return 'Schedule not set';
    }
}
