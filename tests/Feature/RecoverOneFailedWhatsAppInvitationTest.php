<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecoverOneFailedWhatsAppInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_explicit_event_template_and_invitee(): void
    {
        $this->artisan('whatsapp:recover-one-failed-invitation')->assertExitCode(1);
    }

    public function test_single_recipient_dry_run_never_queues_or_changes_failed_claim(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Recovery Test Wedding',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single', 'allowed_people' => 1, 'is_active' => true,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Test Guest',
            'phone' => '0712345678',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'Recovery Invitation',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'recovery_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
        ]);

        DB::table('whatsapp_template_sends')->insert([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'phone_generation' => (int) $invitee->invitation_phone_generation,
            'status' => 'failed',
            'last_error' => 'WhatsApp sending is disabled. Set WHATSAPP_ENABLED=true.',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $args = ['--event' => $event->id, '--template' => $template->id, '--invitee' => $invitee->id];
        $this->artisan('whatsapp:recover-one-failed-invitation', $args)->assertExitCode(0);
        $this->assertSame(1, DB::table('whatsapp_template_sends')->where('status', 'failed')->count());
        $this->assertSame(0, DB::table('jobs')->count());

        $this->artisan('whatsapp:recover-one-failed-invitation', $args + ['--execute' => true])
            ->assertExitCode(1);
        $this->assertSame(1, DB::table('whatsapp_template_sends')->where('status', 'failed')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
