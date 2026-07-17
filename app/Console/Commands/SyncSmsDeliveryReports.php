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
                            {--limit=100 : Maximum number of SMS logs to process}
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
                $query->where(function (Builder $query): void {
                    $query
                        ->whereNull('delivery_report_checked_at')
                        ->orWhere(
                            'delivery_report_checked_at',
                            '<=',
                            now()->subMinutes(5),
                        );
                });
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

        foreach ($logs as $smsLog) {
            $processed++;

            try {
                $result = $smsService->refreshDeliveryReport(
                    (string) $smsLog->provider_message_id,
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
