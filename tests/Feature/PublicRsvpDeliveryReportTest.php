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

    public function test_shared_rsvp_displays_actual_channel_status_times_on_desktop_and_mobile(): void
    {
        $event = $this->reportEvent('Timestamped RSVP Report');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Timestamp Guest',
            'phone' => '255700000012',
        ]);

        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => 'invitation_card',
            'message' => 'Invitation',
            'status' => 'delivered',
            'sent_at' => '2026-11-07 19:18:00',
            'delivered_at' => '2026-11-07 19:44:00',
            'delivery_report_checked_at' => '2026-11-07 20:11:13',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'delivered',
            'sent_at' => '2026-11-07 19:20:00',
            'delivered_at' => '2026-11-07 19:45:00',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('deliveryTimes', fn (array $times): bool =>
                $times[$invitee->id]['sms'] === '07 Nov 2026 19:44'
                && $times[$invitee->id]['whatsapp'] === '07 Nov 2026 19:45'
            )
            ->assertSee('07 Nov 2026 19:44')
            ->assertSee('07 Nov 2026 19:45')
            ->assertDontSee('07 Nov 2026 20:11');
    }

    public function test_shared_rsvp_omits_missing_status_timestamps(): void
    {
        $event = $this->reportEvent('No Timestamps');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'No Timestamp Guest',
            'phone' => '255700000013',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'sent',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('deliveryTimes', fn (array $times): bool =>
                $times[$invitee->id]['sms'] === null
                && $times[$invitee->id]['whatsapp'] === null
            );
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
        $this->assertSame(2, substr_count($html, 'aria-label="WhatsApp"'));
        $this->assertSame(2, substr_count($html, 'viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>WhatsApp</title>'));
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
            ->assertSee('RSVP Time')
            ->assertSee('mobile-rsvp-card')
            ->assertDontSee('<summary>Details</summary>', false)
            ->assertSee('Mobile reply is visible in its own column.')
            ->assertSee('Reply');
    }

    public function test_mobile_rsvp_cards_keep_channel_statuses_and_timestamps_readable(): void
    {
        $event = $this->reportEvent('Mobile Card Delivery');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Responsive Guest',
            'phone' => '255700000056',
            'rsvp_status' => 'attending',
            'last_reply_message' => 'Nitahudhuria',
        ]);

        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => 'invitation_card',
            'message' => 'Invitation',
            'status' => 'sent',
            'sent_at' => '2026-11-07 19:18:00',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertSee('class="mobile-rsvp-cards"', false)
            ->assertSee('class="mobile-card-channels"', false)
            ->assertSee('class="mobile-channel-time"', false)
            ->assertSee('Submitted')
            ->assertSee('07 Nov 2026')
            ->assertSee('Nitahudhuria')
            ->assertSee('Responsive Guest')
            ->assertDontSee('<summary>Details</summary>', false);
    }

    public function test_mobile_report_displays_row_numbers_across_paginated_pages(): void
    {
        $event = $this->reportEvent('Numbered Mobile Report');
        $type = $this->reportCardType($event);

        for ($number = 1; $number <= 51; $number++) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => sprintf('Numbered Guest %03d', $number),
                'phone' => sprintf('25570002%04d', $number),
                'rsvp_status' => 'pending',
            ]);
        }

        $url = $event->generateRsvpShareLink();

        $this->get($url)
            ->assertOk()
            ->assertSee('<span class="mobile-card-number">1.</span>', false)
            ->assertSee('<span class="mobile-card-number">50.</span>', false)
            ->assertDontSee('<span class="mobile-card-number">51.</span>', false);

        $this->get($url . '?page=2')
            ->assertOk()
            ->assertSee('<span class="mobile-card-number">51.</span>', false)
            ->assertDontSee('<span class="mobile-card-number">1.</span>', false);
    }

    public function test_shared_rsvp_link_searches_card_type_and_table_within_event(): void
    {
        $event = $this->reportEvent('Searchable RSVP Event');
        $single = $this->reportCardType($event);
        $vip = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP Family',
            'allowed_people' => 4,
            'is_active' => true,
        ]);

        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $single->id,
            'name' => 'Alice RSVP',
            'phone' => '255700000100',
            'table_number' => 'Table 12',
        ]);
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $vip->id,
            'name' => 'Bob RSVP',
            'phone' => '255700000200',
            'table_number' => 'Table 20',
        ]);

        $other = $this->reportEvent('Other Private RSVP Event');
        Invitee::query()->create([
            'event_id' => $other->id,
            'card_type_id' => $this->reportCardType($other)->id,
            'name' => 'Other Event Guest',
            'phone' => '255700000300',
            'table_number' => 'Table 12',
        ]);

        $url = $event->generateRsvpShareLink();

        $this->get($url.'?search=VIP%20Family')->assertOk()
            ->assertSee('Bob RSVP')->assertDontSee('Alice RSVP')
            ->assertDontSee('Other Event Guest');

        $this->get($url.'?search=Table%2012')->assertOk()
            ->assertSee('Alice RSVP')->assertDontSee('Bob RSVP')
            ->assertDontSee('Other Event Guest');
    }

    public function test_shared_rsvp_search_does_not_expose_hidden_phone_numbers(): void
    {
        $event = $this->reportEvent('Phone Privacy RSVP Event');
        $event->forceFill(['rsvp_share_show_phone' => false])->save();

        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Private Phone Guest',
            'phone' => '255700001234',
        ]);

        $this->get($event->generateRsvpShareLink().'?search=255700001234')
            ->assertOk()->assertDontSee('Private Phone Guest')
            ->assertSee('No invitees match the selected filters.');
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

    public function test_shared_rsvp_filters_by_latest_sms_and_whatsapp_invitation_status(): void
    {
        $event = $this->reportEvent('Delivery Filters Event');
        $type = $this->reportCardType($event);
        $make = function (string $name) use ($event, $type): Invitee {
            return Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => $name,
                'phone' => '255700000999',
            ]);
        };

        $delivered = $make('Delivered SMS Guest');
        $submitted = $make('Submitted SMS Guest');
        $uncontacted = $make('Uncontacted Guest');

        foreach ([[$delivered, 'delivered'], [$submitted, 'sent']] as [$invitee, $status]) {
            SmsLog::query()->create([
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'phone' => $invitee->phone,
                'sms_type' => 'invitation_card',
                'message' => 'Invitation',
                'status' => $status,
            ]);
        }

        // A newer reminder must not override the last invitation status.
        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $delivered->id,
            'phone' => $delivered->phone,
            'sms_type' => 'rsvp_pending_reminder',
            'message' => 'Reminder',
            'status' => 'failed',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $delivered->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'read',
        ]);
        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $submitted->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'failed',
        ]);

        $other = $this->reportEvent('Other Delivery Event');
        Invitee::query()->create([
            'event_id' => $other->id,
            'card_type_id' => $this->reportCardType($other)->id,
            'name' => 'Private Other Event Guest',
            'phone' => '255700000991',
        ]);

        $url = $event->generateRsvpShareLink();

        $this->get($url.'?sms_delivery=delivered&whatsapp_delivery=read')
            ->assertOk()
            ->assertSee('Delivered SMS Guest')
            ->assertDontSee('Submitted SMS Guest')
            ->assertDontSee('Uncontacted Guest')
            ->assertDontSee('Private Other Event Guest')
            ->assertSee('name="sms_delivery"', false)
            ->assertSee('name="whatsapp_delivery"', false);

        $this->get($url.'?sms_delivery=submitted')
            ->assertOk()->assertSee('Submitted SMS Guest')
            ->assertDontSee('Delivered SMS Guest');

        $this->get($url.'?sms_delivery=not_sent')
            ->assertOk()->assertSee('Uncontacted Guest')
            ->assertDontSee('Delivered SMS Guest');

        $this->get($url.'?whatsapp_delivery=failed')
            ->assertOk()->assertSee('Submitted SMS Guest')
            ->assertDontSee('Delivered SMS Guest');

        $this->get($url.'?sms_delivery=invalid')
            ->assertSessionHasErrors('sms_delivery');
    }

    public function test_rsvp_delivery_filter_uses_latest_attempt_not_previous_delivery(): void
    {
        $event = $this->reportEvent('Latest Delivery Attempt');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Resent Invitation Guest',
            'phone' => '255700000045',
        ]);

        foreach (['delivered', 'sent'] as $status) {
            SmsLog::query()->create([
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'phone' => $invitee->phone,
                'sms_type' => 'invitation_card',
                'message' => 'Invitation',
                'status' => $status,
            ]);
        }

        $url = $event->generateRsvpShareLink();
        $this->get($url.'?sms_delivery=submitted')->assertOk()->assertSee('Resent Invitation Guest');
        $this->get($url.'?sms_delivery=delivered')->assertOk()->assertDontSee('Resent Invitation Guest');
    }

    public function test_report_pagination_is_compact_accessible_and_retains_filters(): void
    {
        $event = $this->reportEvent('Compact Pagination Test');
        $type = $this->reportCardType($event);

        for ($number = 1; $number <= 51; $number++) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => sprintf('Pagination Guest %03d', $number),
                'phone' => sprintf('25570003%04d', $number),
                'rsvp_status' => 'pending',
            ]);
        }

        $url = $event->generateRsvpShareLink();

        $this->get($url . '?status=pending')
            ->assertOk()
            ->assertSee('aria-label="Invitee report pages"', false)
            ->assertSee('Page 1 of 2')
            ->assertSee('Showing 1 to 50 of 51 results')
            ->assertSee('status=pending&amp;page=2', false)
            ->assertDontSee('<svg class="w-5 h-5"', false);

        $this->get($url . '?status=pending&page=2')
            ->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('Showing 51 to 51 of 51 results')
            ->assertSee('rel="prev"', false)
            ->assertDontSee('<svg class="w-5 h-5"', false);
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

    public function test_search_uses_portable_like_escape_and_treats_wildcards_literally(): void
    {
        $event = $this->reportEvent('Safe PostgreSQL Search');
        $type = $this->reportCardType($event);

        foreach (['Ngoyi % Family', 'Ngoyi_Committee', 'Ngoyi!VIP', 'Ngoyi Another', 'Other Guest'] as $name) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => $name,
                'phone' => '255700000111',
            ]);
        }

        $queries = [];
        \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'LOWER(COALESCE(name')) {
                $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            }
        });

        $url = $event->generateRsvpShareLink();
        $this->get($url . '?search=ngoyi')
            ->assertOk()
            ->assertSee('Ngoyi Another')
            ->assertDontSee('Other Guest');

        $this->get($url . '?search=' . urlencode('Ngoyi %'))
            ->assertOk()
            ->assertSee('Ngoyi % Family')
            ->assertDontSee('Ngoyi Another')
            ->assertDontSee('Ngoyi_Committee');

        $this->get($url . '?search=' . urlencode('Ngoyi_'))
            ->assertOk()
            ->assertSee('Ngoyi_Committee')
            ->assertDontSee('Ngoyi Another');

        $this->get($url . '?search=' . urlencode('Ngoyi!'))
            ->assertOk()
            ->assertSee('Ngoyi!VIP')
            ->assertDontSee('Ngoyi Another');

        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertStringContainsString("ESCAPE '!'", $query['sql']);
            $this->assertSame(5, count($query['bindings']));
            $this->assertStringNotContainsString("ESCAPE '\\\\'", $query['sql']);
        }
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
