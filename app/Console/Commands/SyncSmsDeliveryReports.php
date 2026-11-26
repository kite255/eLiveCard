<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class SyncSmsDeliveryReports extends Command
{
    protected $signature = 'sms:sync-delivery-reports
                            {--limit=25 : Maximum number of SMS logs to process}
                            {--shoot-id= : Sync only one provider shoot ID}';

    protected $description = 'Fetch eLive SMS delivery reports and update SMS log statuses.';

    public function handle(SmsService $smsService): int
    {
        $shootId = trim((string) $this->option('shoot-id'));
        $limit = max(1, min((int) $this->option('limit'), 500));

        $query = SmsLog::query()
            ->whereNotNull('provider_message_id')
            ->where('provider_message_id', '!=', '');

        if ($shootId !== '') {
            $query->where('provider_message_id', $shootId);
        } else {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNull('status')
                    ->orWhereIn('status', [
                        'accepted',
                        'submitted',
                        'sent',
                        'success',
                        'pending',
                        'queued',
                        'processing',
                        'sending',
                    ]);
            });

            if (\Schema::hasColumn('sms_logs', 'delivery_report_checked_at')) {
                // Newly sent messages need prompt feedback. Older unresolved
                // messages are still retried, but with increasing cooldowns.
                // Use sent_at when available; created_at covers legacy logs.
                $now = now();
                $recent = $now->copy()->subMinutes(15);
                $hourOld = $now->copy()->subHour();
                $dayOld = $now->copy()->subDay();
                $threeDaysOld = $now->copy()->subDays(3);
                $weekOld = $now->copy()->subDays(7);
                $monthOld = $now->copy()->subDays(30);

                // Retain automatic reconciliation for 30 days. Older messages
                // can still be explicitly refreshed by provider shoot ID.
                $query->whereRaw('COALESCE(sent_at, created_at) >= ?', [$monthOld])
                    ->where(function (Builder $query) use ($now, $recent, $hourOld, $dayOld, $threeDaysOld, $weekOld): void {
                        $query->whereNull('delivery_report_checked_at');
                        $windows = [
                            [null, $recent, 1],
                            [$recent, $hourOld, 5],
                            [$hourOld, $dayOld, 30],
                            [$dayOld, $threeDaysOld, 120],
                            [$threeDaysOld, $weekOld, 360],
                            [$weekOld, null, 1440],
                        ];

                        foreach ($windows as [$upper, $lower, $minutes]) {
                            $query->orWhere(function (Builder $q) use ($upper, $lower, $minutes, $now): void {
                                if ($upper !== null) {
                                    $q->whereRaw('COALESCE(sent_at, created_at) < ?', [$upper]);
                                }
                                if ($lower !== null) {
                                    $q->whereRaw('COALESCE(sent_at, created_at) >= ?', [$lower]);
                                }
                                $q->where('delivery_report_checked_at', '<=', $now->copy()->subMinutes($minutes));
                            });
                        }
                    })
                    ->orderByRaw('CASE WHEN COALESCE(sent_at, created_at) >= ? THEN 0 '
                        .'WHEN COALESCE(sent_at, created_at) >= ? THEN 1 ELSE 2 END', [$recent, $hourOld])
                    ->orderByRaw('CASE WHEN delivery_report_checked_at IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('delivery_report_checked_at');
            }
        }

        $logs = $query
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No SMS delivery reports need synchronization.');

            return self::SUCCESS;
        }

        $processed = 0;
        $successful = 0;
        $failed = 0;

        // A provider shoot ID can represent several recipients. One response
        // is reconciled against all matching recipients by SmsService.
        foreach ($logs->unique('provider_message_id') as $smsLog) {
            $processed++;

            try {
                $result = $smsService->refreshDeliveryReport(
                    (string) $smsLog->provider_message_id,
                    auditRoutineLookup: $shootId !== '',
                );

                if ($result['success'] ?? false) {
                    $successful++;

                    $this->line(
                        "Synced SMS log #{$smsLog->id} "
                        ."({$smsLog->provider_message_id})."
                    );
                } else {
                    $failed++;

                    $this->warn(
                        "Could not sync SMS log #{$smsLog->id}: "
                        .($result['message'] ?? 'Unknown provider error')
                    );
                }
            } catch (\Throwable $e) {
                $failed++;

                report($e);

                Log::warning('SMS delivery synchronization failed', [
                    'sms_log_id' => $smsLog->id,
                    'provider_message_id' => $smsLog->provider_message_id,
                    'error' => $e->getMessage(),
                ]);

                $this->error(
                    "SMS log #{$smsLog->id} failed: {$e->getMessage()}"
                );
            }
        }

        $this->newLine();
        $this->table(
            ['Processed', 'Successful', 'Failed'],
            [[$processed, $successful, $failed]],
        );

        return $failed === $processed
            ? self::FAILURE
            : self::SUCCESS;
    }
}
