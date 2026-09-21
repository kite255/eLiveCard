<?php

namespace App\Jobs;

use App\Models\ContributionRecipient;
use App\Services\ContributionCardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

class GenerateContributionCardJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $recipientId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('contribution-card-'.$this->recipientId))->expireAfter(180)];
    }

    public function handle(ContributionCardService $service): void
    {
        $recipient = ContributionRecipient::query()->find($this->recipientId);

        if ($recipient) {
            $recipient->forceFill([
                'generation_status' => ContributionRecipient::STATUS_PROCESSING,
                'last_error' => null,
            ])->saveQuietly();
            $service->generate($recipient);
        }
    }

    public function failed(Throwable $exception): void
    {
        ContributionRecipient::query()->whereKey($this->recipientId)->update([
            'generation_status' => ContributionRecipient::STATUS_FAILED,
            'last_error' => Str::limit($exception->getMessage(), 1000),
        ]);
    }
}
