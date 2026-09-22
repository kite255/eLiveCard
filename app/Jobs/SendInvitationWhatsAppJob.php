<?php

namespace App\Jobs;

use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SendInvitationWhatsAppJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'communications';

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 10;

    public int $uniqueFor = 300;

    public function __construct(
        public int $inviteeId,
        public string $languageCode = MessageTemplate::LANGUAGE_ENGLISH,
        public ?int $messageTemplateId = null,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return $this->inviteeId.'-'.$this->languageCode.'-'.($this->messageTemplateId ?? 'auto');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('send-invitation-whatsapp-'.$this->inviteeId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(WhatsAppApiCloudService $service): void
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->find($this->inviteeId);

        if (! $invitee) {
            return;
        }

        $alreadySent = $invitee->messageLogs()
            ->where('event_id', $invitee->event_id)
            ->where('channel', 'whatsapp')
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->whereIn('status', [
                'submitted',
                'accepted',
                'sent',
                'delivered',
                'read',
            ])
            ->exists();

        if ($alreadySent) {
            return;
        }

        $service->sendInvitation(
            invitee: $invitee,
            languageCode: $this->languageCode,
            messageTemplateId: $this->messageTemplateId,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $invitee = Invitee::find($this->inviteeId);

        if (! $invitee) {
            return;
        }

        $columns = Schema::getColumnListing($invitee->getTable());
        $message = Str::limit(
            $exception?->getMessage() ?? 'WhatsApp invitation sending failed.',
            1000
        );

        $invitee->forceFill(Arr::only([
            'last_message_channel' => 'whatsapp',
            'last_message_status' => 'failed',
            'message_status' => 'failed',
            'whatsapp_status' => 'failed',
            'whatsapp_error' => $message,
            'failed_at' => now(),
        ], $columns))->saveQuietly();
    }
}
