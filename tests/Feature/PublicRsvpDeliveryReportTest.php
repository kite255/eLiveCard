<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRsvpDeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_displays_separate_invitation_delivery_statuses_and_ignores_reminders(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Wedding Report',
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
            'name' => 'Sample Invitee',
            'phone' => '255700000001',
            'rsvp_status' => 'attending',
            'rsvp_confirmed_at' => now(),
            'last_reply_message' => 'Thank you for the invitation.',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'delivered',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'sms',
            'type' => 'invitation',
            'status' => 'sent',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'reminder',
            'status' => 'failed',
        ]);

        $url = $event->generateRsvpShareLink();

        $this->get($url)
            ->assertOk()
            ->assertViewHas('deliveryStatuses', function (array $statuses) use ($invitee): bool {
                return $statuses[$invitee->id] === [
                    'whatsapp' => 'Delivered',
                    'sms' => 'Submitted',
                ];
            })
            ->assertSee('WhatsApp')
            ->assertSee('SMS')
            ->assertSee('Thank you for the invitation.')
            ->assertDontSee('255700000001');
    }

    public function test_uncontacted_invitee_is_not_misrepresented_as_delivered(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Another Wedding',
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
            'name' => 'No Message Guest',
            'phone' => '255700000002',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('deliveryStatuses', function (array $statuses) use ($invitee): bool {
                return $statuses[$invitee->id] === [
                    'whatsapp' => 'Not Sent',
                    'sms' => 'Not Sent',
                ];
            });
    }

    public function test_channel_heading_groups_whatsapp_and_sms_icons_without_hiding_status_words(): void
    {
        $event = $this->reportEvent('Channel Header Event');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Channel Guest',
            'phone' => '255700000007',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'submitted',
        ]);

        $response = $this->get($event->generateRsvpShareLink())->assertOk();

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'scope="colgroup" class="channel-heading"'));
        $this->assertSame(3, substr_count($html, 'aria-label="WhatsApp"'));
        $this->assertSame(2, substr_count($html, 'aria-label="SMS"'));
        $response->assertSee('CHANNEL')
            ->assertSee('Submitted')
            ->assertSee('Not Sent')
            ->assertSee('Channel Guest');
    }

    public function test_mobile_table_shows_reply_and_response_time_without_expand_controls(): void
    {
        $event = $this->reportEvent('Mobile Event');
        $cardType = $this->reportCardType($event);
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Mobile Guest',
            'phone' => '255700000003',
            'rsvp_status' => 'attending',
            'rsvp_confirmed_at' => now(),
            'last_reply_message' => 'Mobile reply is visible in its own column.',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertSee('Compact invitee RSVP, channel, reply and response time report')
            ->assertSee('WhatsApp')
            ->assertSee('SMS')
            ->assertSee('Response Time')
            ->assertDontSee('<summary>Details</summary>', false)
            ->assertSee('Mobile reply is visible in its own column.')
            ->assertSee('Reply');
    }

    public function test_rsvp_filters_and_search_are_event_scoped(): void
    {
        $event = $this->reportEvent('Filtered Event');
        $type = $this->reportCardType($event);

        foreach ([['Alice Attending', 'attending'], ['Bob Pending', 'pending'], ['Charlie Declined', 'not_attending']] as [$name, $status]) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => $name,
                'phone' => '255700000004',
                'rsvp_status' => $status,
            ]);
        }

        $other = $this->reportEvent('Another Event');
        Invitee::query()->create([
            'event_id' => $other->id,
            'card_type_id' => $this->reportCardType($other)->id,
            'name' => 'Other Event Guest',
            'phone' => '255700000005',
            'rsvp_status' => 'attending',
        ]);

        $url = $event->generateRsvpShareLink();

        $this->get($url . '?search=alice&status=attending')
            ->assertOk()
            ->assertSee('Alice Attending')
            ->assertDontSee('Bob Pending')
            ->assertDontSee('Charlie Declined')
            ->assertDontSee('Other Event Guest');

        $this->get($url . '?status=pending')
            ->assertOk()
            ->assertSee('Bob Pending')
            ->assertDontSee('Alice Attending')
            ->assertDontSee('Other Event Guest');
    }

    public function test_expired_or_disabled_client_report_link_is_rejected(): void
    {
        $event = $this->reportEvent('Protected Event');
        $url = $event->generateRsvpShareLink();

        $event->forceFill(['rsvp_share_expires_at' => now()->subMinute()])->save();

        $this->get($url)->assertGone();

        $event->forceFill(['rsvp_share_expires_at' => null])->save();
        $event->disableRsvpShareLink();

        $this->get($url)->assertNotFound();
    }

    public function test_report_uses_latest_invitation_attempt_and_ignores_other_events(): void
    {
        $event = $this->reportEvent('Message Status Event');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Latest Status Guest',
            'phone' => '255700000006',
        ]);

        foreach (['delivered', 'failed'] as $status) {
            MessageLog::query()->create([
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'channel' => 'whatsapp',
                'type' => 'invitation',
                'status' => $status,
            ]);
        }

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'thank_you',
            'status' => 'read',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('deliveryStatuses', fn (array $statuses): bool =>
                $statuses[$invitee->id]['whatsapp'] === 'Failed'
            );
    }

    private function reportEvent(string $title): Event
    {
        return Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function reportCardType(Event $event): CardType
    {
        return CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);
    }
}
