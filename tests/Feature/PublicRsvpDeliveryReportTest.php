<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageLog;
use App\Models\SmsLog;
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
        $this->assertSame(3, substr_count($html, 'viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>WhatsApp</title>'));
        $this->assertStringNotContainsString('M8 24l2-5a11', $html);
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

    public function test_client_report_paginates_fifty_invitees_without_losing_filters(): void
    {
        $event = $this->reportEvent('Paginated Report');
        $type = $this->reportCardType($event);

        for ($index = 1; $index <= 52; $index++) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => sprintf('Test Guest %03d', $index),
                'phone' => sprintf('25570001%04d', $index),
                'rsvp_status' => 'pending',
            ]);
        }

        $url = $event->generateRsvpShareLink();

        $first = $this->get($url . '?status=pending')
            ->assertOk()
            ->assertSee('Test Guest 001')
            ->assertDontSee('Test Guest 052');
        $first->assertViewHas('invitees', fn ($paginated) =>
            $paginated->total() === 52
            && $paginated->count() === 50
            && $paginated->lastPage() === 2
        );

        $this->get($url . '?status=pending&page=2')
            ->assertOk()
            ->assertSee('Test Guest 052')
            ->assertDontSee('Test Guest 001')
            ->assertViewHas('invitees', fn ($paginated) =>
                $paginated->total() === 52
                && $paginated->count() === 2
                && $paginated->currentPage() === 2
            );
    }

    public function test_sms_delivery_uses_invitation_logs_not_newer_reminders(): void
    {
        $event = $this->reportEvent('Invitation SMS Report');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'SMS Guest',
            'phone' => '255700000088',
        ]);

        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => 'invitation',
            'message' => 'Event invitation',
            'status' => 'delivered',
        ]);

        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => 'rsvp_pending_reminder',
            'message' => 'Reminder message',
            'status' => 'failed',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('deliveryStatuses', fn (array $statuses): bool =>
                $statuses[$invitee->id]['sms'] === 'Delivered'
                && $statuses[$invitee->id]['whatsapp'] === 'Not Sent'
            );
    }

    public function test_client_reply_is_html_escaped_and_phone_stays_hidden_by_default(): void
    {
        $event = $this->reportEvent('Private Client Report');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Safe Reply Guest',
            'phone' => '255700000099',
            'last_reply_message' => '<script>alert("injected")</script>',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("injected")</script>', false)
            ->assertDontSee($invitee->phone);
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
