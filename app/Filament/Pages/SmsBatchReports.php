<?php

namespace App\Filament\Pages;

use App\Models\SmsLog;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SmsBatchReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'SMS Batch Reports';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.sms-batch-reports';

    public function getTitle(): string|Htmlable
    {
        return 'SMS Batch Reports';
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    public function getBatchReportsProperty(): Collection
    {
        return DB::table('sms_logs')
            ->leftJoin('events', 'events.id', '=', 'sms_logs.event_id')
            ->leftJoin('users', 'users.id', '=', 'sms_logs.sent_by_user_id')
            ->select([
                DB::raw("COALESCE(sms_logs.batch_id, 'NO-BATCH') as batch_id"),
                DB::raw('MIN(sms_logs.id) as first_log_id'),
                DB::raw('MAX(sms_logs.id) as last_log_id'),
                DB::raw('MAX(events.title) as event_title'),
                DB::raw('MAX(sms_logs.sms_type) as sms_type'),
                DB::raw('MAX(sms_logs.send_source) as send_source'),
                DB::raw("COALESCE(MAX(users.name), 'System') as sent_by"),
                DB::raw('COUNT(*) as total_sms'),
                DB::raw("SUM(CASE WHEN sms_logs.status = 'sent' THEN 1 ELSE 0 END) as sent_count"),
                DB::raw("SUM(CASE WHEN sms_logs.status = 'delivered' THEN 1 ELSE 0 END) as delivered_count"),
                DB::raw("SUM(CASE WHEN sms_logs.status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                DB::raw("SUM(CASE WHEN sms_logs.status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw('MIN(sms_logs.created_at) as started_at'),
                DB::raw('MAX(sms_logs.created_at) as latest_at'),
            ])
            ->groupBy(DB::raw("COALESCE(sms_logs.batch_id, 'NO-BATCH')"))
            ->orderByDesc(DB::raw('MAX(sms_logs.created_at)'))
            ->limit(100)
            ->get();
    }

    public function formatSmsType(?string $smsType): string
    {
        return match ($smsType) {
            SmsLog::TYPE_INVITATION => 'Invitation',
            SmsLog::TYPE_RSVP_PENDING_REMINDER => 'RSVP Reminder',
            SmsLog::TYPE_ATTENDING_REMINDER => 'One Day Before',
            SmsLog::TYPE_EVENT_DAY_REMINDER => 'Event Day',
            default => $smsType ? ucfirst(str_replace('_', ' ', $smsType)) : 'Unknown',
        };
    }

    public function formatSource(?string $source): string
    {
        return SmsLog::sources()[$source] ?? ucfirst((string) $source ?: 'Unknown');
    }

    public function successRate(object $report): string
    {
        $total = (int) $report->total_sms;

        if ($total <= 0) {
            return '0%';
        }

        $successful = (int) $report->sent_count + (int) $report->delivered_count;

        return round(($successful / $total) * 100) . '%';
    }
}