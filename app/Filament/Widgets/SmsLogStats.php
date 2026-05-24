<?php

namespace App\Filament\Widgets;

use App\Models\SmsLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SmsLogStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalSms = SmsLog::query()->count();

        $sentSms = SmsLog::query()
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();

        $failedSms = SmsLog::query()
            ->where('status', SmsLog::STATUS_FAILED)
            ->count();

        $pendingSms = SmsLog::query()
            ->where('status', SmsLog::STATUS_PENDING)
            ->count();

        $invitationSms = SmsLog::query()
            ->where('sms_type', SmsLog::TYPE_INVITATION)
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();

        $rsvpReminderSms = SmsLog::query()
            ->where('sms_type', SmsLog::TYPE_RSVP_PENDING_REMINDER)
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();

        $attendingReminderSms = SmsLog::query()
            ->where('sms_type', SmsLog::TYPE_ATTENDING_REMINDER)
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();

        $eventDaySms = SmsLog::query()
            ->where('sms_type', SmsLog::TYPE_EVENT_DAY_REMINDER)
            ->whereIn('status', [
                SmsLog::STATUS_SENT,
                SmsLog::STATUS_DELIVERED,
            ])
            ->count();

        return [
            Stat::make('Total SMS Logs', number_format($totalSms))
                ->description('All SMS attempts')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('Sent SMS', number_format($sentSms))
                ->description('Successfully submitted')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed SMS', number_format($failedSms))
                ->description('Needs attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Pending SMS', number_format($pendingSms))
                ->description('Waiting for provider response')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Invitation SMS', number_format($invitationSms))
                ->description('First invitation messages')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('info'),

            Stat::make('RSVP Reminders', number_format($rsvpReminderSms))
                ->description('Pending RSVP reminders')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('warning'),

            Stat::make('One Day Before', number_format($attendingReminderSms))
                ->description('Attending invitees reminders')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Event Day SMS', number_format($eventDaySms))
                ->description('Final event reminders')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('success'),
        ];
    }
}