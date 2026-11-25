<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\WhatsAppInvitationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppInvitationQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'WhatsApp Queue Claim Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'name' => 'Test Queue Recipient',
            'phone' => '255700000123',
        ]);

        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'Invite',
            'content' => 'Welcome',
            'whatsapp_template_name' => 'test_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);

        return [$invitee, $template];
    }

    private function claimQuery(Invitee $invitee, MessageTemplate $template)
    {
        return DB::table('whatsapp_template_sends')
            ->where('event_id', $invitee->event_id)
            ->where('invitee_id', $invitee->id)
            ->where('message_template_id', $template->id)
            ->where('phone_generation', (int) $invitee->invitation_phone_generation);
    }

    public function test_new_claim_queues_one_job_and_duplicate_click_skips_without_requeueing(): void
    {
        Queue::fake();
        [$invitee, $template] = $this->fixture();
        $service = app(WhatsAppInvitationQueueService::class);

        $this->assertSame('queued', $service->queue($invitee, $template)['status']);
        $this->assertSame('skipped', $service->queue($invitee, $template)['status']);

        $this->assertSame(1, $this->claimQuery($invitee, $template)->count());
        $this->assertSame('pending', $this->claimQuery($invitee, $template)->first()->status);
        $this->assertSame(1, (int) $this->claimQuery($invitee, $template)->first()->attempts);

        Queue::assertPushed(SendInvitationWhatsAppJob::class, 1);
        Queue::assertPushed(SendInvitationWhatsAppJob::class, fn ($job) =>
            $job->inviteeId === $invitee->id
            && $job->messageTemplateId === $template->id
            && $job->languageCode === 'sw'
        );
    }

    public function test_failed_only_retry_reuses_existing_claim_and_queues_once(): void
    {
        Queue::fake();
        [$invitee, $template] = $this->fixture();
        $service = app(WhatsAppInvitationQueueService::class);

        $this->assertSame('queued', $service->queue($invitee, $template)['status']);
        Queue::assertPushed(SendInvitationWhatsAppJob::class, 1);

        $this->claimQuery($invitee, $template)->update(['status' => 'failed']);
        $reclaimed = $this->claimQuery($invitee, $template)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'attempts' => DB::raw('attempts + 1'),
                'last_attempt_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertSame(1, $reclaimed);
        $this->assertSame('queued', $service->queue($invitee, $template, true)['status']);
        $this->assertSame(1, $this->claimQuery($invitee, $template)->count());
        $this->assertSame(2, (int) $this->claimQuery($invitee, $template)->first()->attempts);
        Queue::assertPushed(SendInvitationWhatsAppJob::class, 2);
    }

    public function test_claim_is_not_reused_without_explicit_failed_retry(): void
    {
        Queue::fake();
        [$invitee, $template] = $this->fixture();
        $service = app(WhatsAppInvitationQueueService::class);

        $this->assertSame('skipped', $service->queue($invitee, $template, true)['status']);
        Queue::assertNotPushed(SendInvitationWhatsAppJob::class);

        $this->assertSame('queued', $service->queue($invitee, $template)['status']);
        $this->claimQuery($invitee, $template)->update(['status' => 'submitted']);

        $this->assertSame('skipped', $service->queue($invitee, $template)['status']);
        $this->assertSame('skipped', $service->queue($invitee, $template, true)['status']);
        Queue::assertPushed(SendInvitationWhatsAppJob::class, 1);
    }

    public function test_bulk_sender_delegates_new_claim_to_queue_service(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'
        ));

        $start = strpos($source, 'protected function sendEventMessagesFromToolbar(');
        $end = strpos($source, 'protected function sharedPhoneReportHtml(', $start);
        $bulk = substr($source, $start, $end - $start);

        $this->assertStringContainsString(
            "if (\$channel === 'whatsapp' && \$recipientScope === 'retry_failed')",
            $bulk
        );
        $this->assertStringNotContainsString(
            "DB::table('whatsapp_template_sends')->insertOrIgnore(",
            $bulk
        );
        $this->assertStringContainsString(
            "claimAlreadyAcquired: \$channel === 'whatsapp' && \$recipientScope === 'retry_failed'",
            $bulk
        );
        $this->assertStringContainsString(
            'WhatsAppInvitationQueueService::class',
            $source
        );
    }
}
