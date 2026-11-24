<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\WhatsAppApiCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Services\InviteeInvitationService;
use Tests\TestCase;

class WhatsAppTemplateTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_job_marks_a_claim_submitted_once(): void
    {
        [$invitee, $template] = $this->fixture();
        $this->claim($invitee, $template);

        $service = $this->mock(WhatsAppApiCloudService::class);
        $service->shouldReceive('sendInvitation')->once()->andReturn(['messages' => [['id' => 'wamid.test']]]);

        $job = new SendInvitationWhatsAppJob($invitee->id, 'en', $template->id);
        $job->handle($service);
        $job->handle($service);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'submitted',
            'attempts' => 1,
        ]);
    }

    public function test_failed_job_is_recorded_and_not_automatically_resent(): void
    {
        [$invitee, $template] = $this->fixture();
        $this->claim($invitee, $template);

        $service = $this->mock(WhatsAppApiCloudService::class);
        $service->shouldReceive('sendInvitation')->once()->andThrow(new \RuntimeException('Meta rejected the request'));

        $job = new SendInvitationWhatsAppJob($invitee->id, 'en', $template->id);
        $job->handle($service);
        $job->handle($service);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'failed',
            'attempts' => 1,
        ]);
    }

    public function test_failed_only_retry_claim_does_not_reclaim_submitted_messages(): void
    {
        [$invitee, $template] = $this->fixture();
        $this->claim($invitee, $template);

        $this->assertSame(0, $this->retryClaim($invitee, $template));

        DB::table('whatsapp_template_sends')->where('invitee_id', $invitee->id)->update(['status' => 'failed']);
        $this->assertSame(1, $this->retryClaim($invitee, $template));
        $this->assertSame(0, $this->retryClaim($invitee, $template));

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'pending',
            'attempts' => 2,
        ]);
    }

    public function test_phone_update_keeps_old_whatsapp_claim_and_enables_new_generation(): void
    {
        Queue::fake();
        [$invitee, $template] = $this->fixture();
        $this->claim($invitee, $template);

        DB::table('whatsapp_template_sends')->where('invitee_id', $invitee->id)
            ->update(['status' => 'submitted']);

        $invitee->forceFill([
            'whatsapp_status' => 'delivered',
            'whatsapp_sent_at' => now()->subHour(),
        ])->save();

        $invitee->phone = '0712345678';
        $invitee->save();
        $invitee->refresh();

        $this->assertSame('255712345678', $invitee->phone);
        $this->assertSame(1, $invitee->invitation_phone_generation);
        $this->assertSame('not_sent', $invitee->whatsapp_status);
        $this->assertNull($invitee->whatsapp_sent_at);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id, 'phone_generation' => 0,
            'status' => 'submitted',
        ]);

        $invitee->event->update(['default_whatsapp_template_id' => $template->id]);
        $result = app(InviteeInvitationService::class)->send($invitee->event, $invitee, 'whatsapp');
        $this->assertSame('queued', $result['status']);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id, 'phone_generation' => 1,
            'status' => 'pending',
        ]);

        Queue::assertPushed(SendInvitationWhatsAppJob::class, function ($job): bool {
            return $job->phoneGeneration === 1;
        });

        $this->assertSame(2, DB::table('whatsapp_template_sends')
            ->where('invitee_id', $invitee->id)->count());

        $again = app(InviteeInvitationService::class)->send($invitee->event, $invitee->fresh(), 'whatsapp');
        $this->assertSame('skipped', $again['status']);
        Queue::assertPushed(SendInvitationWhatsAppJob::class, 1);
    }

    public function test_formatting_change_does_not_reset_whatsapp_status_or_generation(): void
    {
        [$invitee, $template] = $this->fixture();
        $invitee->forceFill(['whatsapp_status' => 'delivered'])->save();

        $invitee->phone = '+255 754 123 456';
        $invitee->save();
        $invitee->refresh();

        $this->assertSame(0, $invitee->invitation_phone_generation);
        $this->assertNull($invitee->invitation_phone_changed_at);
        $this->assertSame('delivered', $invitee->whatsapp_status);
    }

    public function test_stale_queued_invitation_does_not_send_to_new_phone(): void
    {
        [$invitee, $template] = $this->fixture();
        $this->claim($invitee, $template);

        $invitee->phone = '0712345678';
        $invitee->save();

        $service = $this->mock(WhatsAppApiCloudService::class);
        $service->shouldNotReceive('sendInvitation');

        (new SendInvitationWhatsAppJob($invitee->id, 'en', $template->id, 0))
            ->handle($service);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'invitee_id' => $invitee->id, 'phone_generation' => 0,
            'status' => 'pending',
        ]);
    }

    private function fixture(): array
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Test Wedding',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Test Guest',
            'phone' => '255754123456',
            'short_code' => 'TSTABC',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'Test Invitation',
            'content' => 'Hello',
            'whatsapp_template_name' => 'event_invitation_en',
            'whatsapp_language_code' => 'en',
            'status' => 'active',
        ]);

        return [$invitee, $template];
    }

    private function claim(Invitee $invitee, MessageTemplate $template): void
    {
        DB::table('whatsapp_template_sends')->insert([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function retryClaim(Invitee $invitee, MessageTemplate $template): int
    {
        return DB::table('whatsapp_template_sends')
            ->where('event_id', $invitee->event_id)
            ->where('invitee_id', $invitee->id)
            ->where('message_template_id', $template->id)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'attempts' => DB::raw('attempts + 1'),
                'last_attempt_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
