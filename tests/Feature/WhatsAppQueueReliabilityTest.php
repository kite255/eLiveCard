<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\WhatsAppInvitationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppQueueReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_is_read_only_and_rejects_invalid_threshold(): void
    {
        $this->artisan('whatsapp:monitor-invitations', ['--minutes' => 30])->assertExitCode(0);
        $this->artisan('whatsapp:monitor-invitations', ['--minutes' => 0])->assertExitCode(1);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_database_queue_claim_and_job_are_created_once(): void
    {
        config()->set('queue.default', 'database');
        config()->set('messaging.whatsapp_queue', 'communications');

        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Atomic Send',
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
            'name' => 'Test',
            'phone' => '255700000123',
        ]);
        $template = MessageTemplate::query()->create([
            'event_id' => null,
            'channel' => 'whatsapp',
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'Invitation',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'test_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
        ]);
        $service = app(WhatsAppInvitationQueueService::class);

        $first = $service->queue($invitee, $template);
        $this->assertSame('queued', $first['status']);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'communications')->count());
        $this->assertSame(1, DB::table('whatsapp_template_sends')->where('status', 'pending')->count());

        $second = $service->queue($invitee, $template);
        $this->assertSame('skipped', $second['status']);
        $this->assertSame(1, DB::table('jobs')->count());
    }
}
