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

class AuditFailedWhatsAppInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_valid_event_and_template_arguments(): void
    {
        $this->artisan('whatsapp:audit-failed-invitations')->assertExitCode(1);
        $this->artisan('whatsapp:audit-failed-invitations', [
            '--event' => 'invalid', '--template' => 1,
        ])->assertExitCode(1);
    }

    public function test_it_audits_failed_records_without_mutating_them_or_enqueuing_messages(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Recovery Audit Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);
        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'type' => MessageTemplate::TYPE_INVITATION,
            'name' => 'Invitation',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'recovery_invitation',
            'whatsapp_language_code' => 'sw',
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Test Recipient',
            'phone' => '0712345678',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
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

        // Some successful channel logs belong to an event but not an invitee.
        // They must not crash the failed-invitation audit when indexed.
        DB::table('message_logs')->insert([
            'event_id' => $event->id,
            'invitee_id' => null,
            'channel' => 'whatsapp',
            'type' => MessageTemplate::TYPE_INVITATION,
            'phone' => '255700111222',
            'message' => 'Previously submitted invitation',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $args = ['--event' => $event->id, '--template' => $template->id];
        $this->artisan('whatsapp:audit-failed-invitations', $args)->assertExitCode(0);
        $this->assertSame(1, DB::table('whatsapp_template_sends')->where('status', 'failed')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('message_logs')->count());
    }
}
