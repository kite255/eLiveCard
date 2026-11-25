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

class RecoverOrphanWhatsAppInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_requires_event_and_template(): void
    {
        $this->artisan('whatsapp:recover-orphan-invitations')->assertExitCode(1);
        $this->artisan('whatsapp:recover-orphan-invitations', [
            '--event' => 16, '--template' => 0,
        ])->assertExitCode(1);
    }

    public function test_dry_run_does_not_change_claims_or_create_jobs(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Recovery Test',
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
            'name' => 'Pending Recipient',
            'phone' => '255700000123',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'Invitation',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'audit_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
        ]);
        DB::table('whatsapp_template_sends')->insert([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'phone_generation' => (int) $invitee->invitation_phone_generation,
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $arguments = ['--event' => $event->id, '--template' => $template->id];
        $this->artisan('whatsapp:recover-orphan-invitations', $arguments)->assertExitCode(0);
        $this->assertSame(1, DB::table('whatsapp_template_sends')->where('status', 'pending')->count());
        $this->assertSame(0, DB::table('jobs')->count());

        $this->artisan('whatsapp:recover-orphan-invitations', $arguments + [
            '--execute' => true, '--expected' => 1,
        ])->assertExitCode(1);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
