<?php

namespace App\Filament\Resources\EventResource\Widgets;

use App\Models\Event;
use App\Models\MessageLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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

        $inviteesCount = $this->relationshipCount($event, 'invitees');
        $generatedCardsCount = $this->generatedCardsCount($event);
        $checkedInInvitees = $this->checkedInInviteesCount($event);

        $rsvpAttendingCount = $this->inviteeStatusCount(
            $event,
            ['attending', 'confirmed', 'yes'],
        );

        $rsvpNotAttendingCount = $this->inviteeStatusCount(
            $event,
            ['not_attending', 'declined', 'no'],
        );

        $respondedCount = $rsvpAttendingCount + $rsvpNotAttendingCount;
        $rsvpPendingCount = max($inviteesCount - $respondedCount, 0);

        $responseRate = $inviteesCount > 0
            ? round(($respondedCount / $inviteesCount) * 100)
            : 0;

        $checkInRate = $inviteesCount > 0
            ? round(($checkedInInvitees / $inviteesCount) * 100)
            : 0;

        $totalAllowedGuests = $this->totalAllowedGuests($event);
        $checkedInGuests = $this->checkedInGuests($event);

        $smsSentCount = $this->messageLogCount(
            $eventId,
            'sms',
            ['sent', 'delivered', 'read', 'accepted', 'logged', 'submitted', 'success'],
        );

        $smsFailedCount = $this->messageLogCount(
            $eventId,
            'sms',
            ['failed', 'rejected', 'error'],
        );

        $whatsAppSentCount = $this->messageLogCount(
            $eventId,
            'whatsapp',
            ['sent', 'delivered', 'read', 'accepted', 'logged', 'replied', 'success'],
        );

        $whatsAppFailedCount = $this->messageLogCount(
            $eventId,
            'whatsapp',
            ['failed', 'rejected', 'error'],
        );

        $communicationFailedCount = $smsFailedCount + $whatsAppFailedCount;

        return [
            Stat::make('Invitees', number_format($inviteesCount))
                ->description(number_format($totalAllowedGuests).' allowed guests')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('RSVP Attending', number_format($rsvpAttendingCount))
                ->description(number_format($rsvpPendingCount).' pending responses')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('RSVP Response Rate', $responseRate.'%')
                ->description(number_format($respondedCount).' of '.number_format($inviteesCount).' responded')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($responseRate >= 70 ? 'success' : ($responseRate >= 40 ? 'warning' : 'gray')),

            Stat::make('Cards Ready', number_format($generatedCardsCount))
                ->description(
                    number_format(max($inviteesCount - $generatedCardsCount, 0))
                    .' invitees without cards'
                )
                ->descriptionIcon('heroicon-m-identification')
                ->color($generatedCardsCount >= $inviteesCount && $inviteesCount > 0 ? 'success' : 'warning'),

            Stat::make('Checked-In Invitees', number_format($checkedInInvitees))
                ->description($checkInRate.'% check-in progress')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color($checkedInInvitees > 0 ? 'info' : 'gray'),

            Stat::make('Guests Checked In', number_format($checkedInGuests))
                ->description(number_format(max($totalAllowedGuests - $checkedInGuests, 0)).' remaining capacity')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($checkedInGuests > 0 ? 'primary' : 'gray'),

            Stat::make('Messages Sent', number_format($smsSentCount + $whatsAppSentCount))
                ->description(
                    number_format($smsSentCount).' SMS • '
                    .number_format($whatsAppSentCount).' WhatsApp'
                )
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),

            Stat::make('Attention Required', number_format($communicationFailedCount))
                ->description(
                    $communicationFailedCount > 0
                        ? 'Failed SMS or WhatsApp messages'
                        : $this->eventScheduleDescription($event)
                )
                ->descriptionIcon(
                    $communicationFailedCount > 0
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-calendar-days'
                )
                ->color($communicationFailedCount > 0 ? 'danger' : $this->eventStatusColor($event)),
        ];
    }

    private function relationshipCount(Event $event, string $relationship): int
    {
        if (! method_exists($event, $relationship)) {
            return 0;
        }

        try {
            return (int) $event->{$relationship}()->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function generatedCardsCount(Event $event): int
    {
        if (! method_exists($event, 'generatedCards')) {
            return 0;
        }

        try {
            $query = $event->generatedCards();

            if (Schema::hasColumn('generated_cards', 'status')) {
                $query->whereIn('status', [
                    'generated',
                    'sent',
                ]);
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function inviteeStatusCount(
        Event $event,
        array $statuses,
    ): int {
        if (
            ! method_exists($event, 'invitees')
            || ! Schema::hasTable('invitees')
            || ! Schema::hasColumn('invitees', 'rsvp_status')
        ) {
            return 0;
        }

        try {
            return (int) $event->invitees()
                ->whereIn('rsvp_status', $statuses)
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function checkedInInviteesCount(Event $event): int
    {
        if (! method_exists($event, 'invitees')) {
            return $this->relationshipCount($event, 'checkIns');
        }

        try {
            $query = $event->invitees();

            if (Schema::hasColumn('invitees', 'checked_in_count')) {
                return (int) $query
                    ->where('checked_in_count', '>', 0)
                    ->count();
            }

            if (Schema::hasColumn('invitees', 'check_in_status')) {
                return (int) $query
                    ->whereIn('check_in_status', [
                        'checked_in',
                        'partial',
                    ])
                    ->count();
            }

            if (Schema::hasColumn('invitees', 'checked_in_at')) {
                return (int) $query
                    ->whereNotNull('checked_in_at')
                    ->count();
            }

            return $this->relationshipCount($event, 'checkIns');
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function totalAllowedGuests(Event $event): int
    {
        if (
            ! method_exists($event, 'invitees')
            || ! Schema::hasTable('invitees')
        ) {
            return 0;
        }

        try {
            foreach (['allowed_guests', 'guest_limit', 'allowed_people'] as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    return (int) $event->invitees()->sum($column);
                }
            }

            return (int) $event->invitees()->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function checkedInGuests(Event $event): int
    {
        if (
            method_exists($event, 'invitees')
            && Schema::hasTable('invitees')
        ) {
            try {
                foreach (['checked_in_count', 'checked_in_guests'] as $column) {
                    if (Schema::hasColumn('invitees', $column)) {
                        return (int) $event->invitees()->sum($column);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (
            ! method_exists($event, 'checkIns')
            || ! Schema::hasTable('check_ins')
        ) {
            return 0;
        }

        try {
            $query = $event->checkIns();

            foreach ([
                'guests_checked_in',
                'guest_count',
                'guests_count',
                'checked_in_count',
                'quantity',
            ] as $column) {
                if (Schema::hasColumn('check_ins', $column)) {
                    return (int) $query->sum($column);
                }
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function messageLogCount(
        int|string $eventId,
        string $channel,
        array $statuses,
    ): int {
        if (! Schema::hasTable('message_logs')) {
            return 0;
        }

        try {
            return (int) MessageLog::query()
                ->when(
                    Schema::hasColumn('message_logs', 'event_id'),
                    fn (Builder $query): Builder => $query
                        ->where('event_id', $eventId),
                )
                ->when(
                    Schema::hasColumn('message_logs', 'channel'),
                    fn (Builder $query): Builder => $query
                        ->where('channel', $channel),
                )
                ->when(
                    Schema::hasColumn('message_logs', 'status'),
                    fn (Builder $query): Builder => $query
                        ->whereIn('status', $statuses),
                )
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    private function eventScheduleDescription(Event $event): string
    {
        $date = $event->event_date_display
            ?? ($event->event_date?->format('d M Y') ?? null);

        $time = $event->time_display
            ?? ($event->start_time ?? null);

        if ($date && $time) {
            return $date.' • '.$time;
        }

        if ($date) {
            return (string) $date;
        }

        if ($time) {
            return (string) $time;
        }

        return 'Schedule not set';
    }

    private function eventStatusColor(Event $event): string
    {
        return match ($event->status) {
            Event::STATUS_ACTIVE => 'success',
            Event::STATUS_COMPLETED => 'info',
            Event::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
