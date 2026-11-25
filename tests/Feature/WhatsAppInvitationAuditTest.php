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

class WhatsAppInvitationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_counts_pending_and_never_changes_tracking_or_dispatches(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Audit Event',
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
            'name' => 'Audit Recipient',
            'phone' => '255700000123',
        ]);
        $template = MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
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
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $this->artisan('whatsapp:audit-invitations', ['--event' => $event->id, '--limit' => 10])
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('whatsapp_template_sends')
            ->where('event_id', $event->id)
            ->where('status', 'pending')
            ->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_audit_rejects_invalid_options(): void
    {
        $this->artisan('whatsapp:audit-invitations', ['--event' => 'invalid'])
            ->assertExitCode(2);
        $this->artisan('whatsapp:audit-invitations', ['--limit' => 0])
            ->assertExitCode(2);
    }

    public function test_whatsapp_send_requires_confirmation_and_counts_unsent_invitees(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'
        ));
        $this->assertStringContainsString("->modalSubmitActionLabel('Confirm & Send')", $source);
        $this->assertStringContainsString(".' unsent invitees?'", $source);
        $this->assertStringContainsString('protected function whatsappUnsentCount(', $source);
        $this->assertStringContainsString('protected function whatsappUnsentQuery(', $source);
        $this->assertStringNotContainsString("Placeholder::make('whatsapp_send_review')", $source);
    }
}
