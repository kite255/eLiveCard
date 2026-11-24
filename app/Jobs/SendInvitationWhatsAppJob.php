<?php

namespace App\Jobs;

use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Container\Container;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        public ?int $phoneGeneration = null,
    ) {
        $queue = Container::getInstance()->bound('config')
            ? (string) config('messaging.whatsapp_queue', self::QUEUE)
            : self::QUEUE;
        $this->onQueue($queue);
    }

    public function uniqueId(): string
    {
        return $this->inviteeId.'-'.$this->languageCode.'-'.($this->messageTemplateId ?? 'auto').'-'.($this->phoneGeneration ?? 0);
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

        // A queued invitation for the former number must not be redirected
        // silently to a newly edited phone number.
        if ($this->phoneGeneration !== null
            && $this->phoneGeneration !== (int) $invitee->invitation_phone_generation) {
            return;
        }

        // The bulk sender claims the template/invitee combination before dispatch.
        $tracking = $this->messageTemplateId && Schema::hasTable('whatsapp_template_sends')
            ? DB::table('whatsapp_template_sends')
                ->where('event_id', $invitee->event_id)
                ->where('invitee_id', $invitee->id)
                ->where('message_template_id', $this->messageTemplateId)
                ->where('phone_generation', $this->phoneGeneration ?? 0)
                ->first()
            : null;

        // Keep legacy protection for invitation jobs dispatched outside the bulk toolbar.
        if (! $tracking) {
            $alreadySent = $invitee->messageLogs()
                ->where('event_id', $invitee->event_id)
                ->where('channel', 'whatsapp')
                ->where('type', MessageTemplate::TYPE_INVITATION)
                ->whereIn('status', ['submitted', 'accepted', 'sent', 'delivered', 'read'])
                ->when($invitee->invitation_phone_changed_at, fn ($query) =>
                    $query->where('id', '>', (int) $invitee->invitation_message_reset_log_id))
                ->exists();

            if ($alreadySent) {
                return;
            }
        }

        if ($tracking && $tracking->status !== 'pending') {
            return;
        }

        try {
            $service->sendInvitation(
                invitee: $invitee,
                languageCode: $this->languageCode,
                messageTemplateId: $this->messageTemplateId,
            );

            if ($tracking) {
                DB::table('whatsapp_template_sends')->where('id', $tracking->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'submitted', 'last_error' => null, 'updated_at' => now()]);
            }
        } catch (Throwable $exception) {
            if ($tracking) {
                DB::table('whatsapp_template_sends')->where('id', $tracking->id)
                    ->update(['status' => 'failed',
                        'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                        'updated_at' => now()]);
                return; // Failed-only retry is initiated explicitly by an admin.
            }
            throw $exception;
        }
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
