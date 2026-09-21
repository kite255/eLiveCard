<?php

namespace App\Jobs;

use App\Models\ContributionRecipient;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

class SendContributionCardJob implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'communications';

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(public int $recipientId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('send-contribution-card-'.$this->recipientId))->expireAfter(120)];
    }

    public function handle(WhatsAppApiCloudService $service): void
    {
        $recipient = ContributionRecipient::with('campaign.event')->find($this->recipientId);

        if ($recipient) {
            $service->sendContributionRecipient($recipient);
        }
    }

    public function failed(Throwable $exception): void
    {
        ContributionRecipient::query()->whereKey($this->recipientId)->update([
            'send_status' => ContributionRecipient::STATUS_FAILED,
            'whatsapp_status' => ContributionRecipient::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => Str::limit($exception->getMessage(), 1000),
        ]);
    }
}
