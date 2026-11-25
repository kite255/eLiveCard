<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageLog;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            ->assertSee('255700000001');
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

    public function test_shared_rsvp_link_shows_receipt_availability_and_updates_after_sync(): void
    {
        $event = $this->reportEvent('Invitation Delivery Report');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'RSVP Guest',
            'phone' => '255700000013',
        ]);
        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => SmsLog::TYPE_INVITATION_CARD,
            'message' => 'Invitation',
            'status' => 'sent',
            'provider_status' => 'delivery_report_unavailable',
            'provider_message_id' => 'private-test-reference',
            'error_message' => 'Private provider error',
        ]);

        $url = $event->generateRsvpShareLink();
        $unconfirmed = $this->get($url.'?sms_delivery=submitted')
            ->assertOk()
            ->assertSee('RSVP Guest')
            ->assertSee('Delivery report unavailable')
            ->assertDontSee('Private provider error')
            ->assertDontSee('private-test-reference');
        $this->assertSame(3, substr_count($unconfirmed->getContent(), 'Delivery report unavailable'));

        config([
            'services.sms.base_url' => 'https://sms.example.test',
            'services.elive_sms.api_key' => 'test-key',
            'services.elive_sms.api_secret' => 'test-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake(['*/deliver/private-test-reference' => Http::response(['data' => [[
            'status' => 'Delivered',
            'mobile' => $invitee->phone,
            'deliveredAt' => '2026-11-22 16:45:00',
        ]]])]);
        $this->assertTrue(app(SmsService::class)->refreshDeliveryReport('private-test-reference')['success']);

        $this->get($url.'?sms_delivery=delivered')
            ->assertOk()
            ->assertSee('RSVP Guest')
            ->assertSee('22 Nov 2026 16:45')
            ->assertDontSee('Delivery report unavailable');
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

        $this->assertSame(1, substr_count($html, 'scope="colgroup" class="channel-heading desktop-channel-heading"'));
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
            ->assertSee('Compact invitee RSVP, delivery and reply report')
            ->assertSee('WhatsApp')
            ->assertSee('SMS')
            ->assertSee('RSVP ')
            ->assertSee('mobile-compact-table')
            ->assertDontSee('<summary>Details</summary>', false)
            ->assertSee('Mobile reply is visible in its own column.')
            ->assertSee('Reply');
    }

    public function test_live_search_keeps_existing_search_and_delivery_filters_on_shared_report(): void
    {
        $event = $this->reportEvent('Live Search Event');
        $type = $this->reportCardType($event);

        foreach (['Asha Wadi', 'Austin Wanjango'] as $name) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => $name,
                'phone' => '255700000006',
            ]);
        }

        $url = $event->generateRsvpShareLink();
        $this->get($url)->assertOk()
            ->assertSee('id="rsvp-report-filters"', false)
            ->assertSee('id="rsvp-live-search"', false)
            ->assertSee('id="rsvp-live-results"', false)
            ->assertSee('id="rsvp-live-status"', false)
            ->assertSee("search.addEventListener('input', schedule)", false)
            ->assertSee('450', false)
            ->assertSee("results.addEventListener('click'", false)
            ->assertSee("window.addEventListener('popstate'", false)
            ->assertSee('id="rsvp-live-stats"', false)
            ->assertSee('id="rsvp-live-response-count"', false)
            ->assertSee('const refreshIntervalMs = 20000;', false)
            ->assertSee("load(new URL(window.location.href), 'none', true)", false)
            ->assertSee("currentStats.innerHTML = nextStats.innerHTML", false)
            ->assertSee("currentResponseCount.textContent = nextResponseCount.textContent", false)
            ->assertSee("if (silent && (activeRequest || debounceTimer || document.hidden)) return;", false);

        // The live update uses the same GET endpoint and server-side access rules.
        $this->get($url.'?search=asha')->assertOk()
            ->assertSee('Asha Wadi')
            ->assertDontSee('Austin Wanjango');
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
            ->assertSee('class="mobile-rsvp-cards mobile-rsvp-columns"', false)
            ->assertSee('class="mobile-compact-table"', false)
            ->assertSee('class="mobile-compact-message"', false)
            ->assertSee('Submitted')
            ->assertSee('07 Nov 2026')
            ->assertSee('Nitahudhuria')
            ->assertSee('Responsive Guest')
            ->assertDontSee('<summary>Details</summary>', false);
    }

    public function test_mobile_uses_full_desktop_columns_with_horizontal_scroll(): void
    {
        $event = $this->reportEvent('Mobile All Columns Event');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'All Columns Guest',
            'phone' => '255700009123',
            'rsvp_status' => 'attending',
        ]);

        $response = $this->get($event->generateRsvpShareLink())->assertOk();
        $response->assertSee('Swipe left or right to view all report columns.')
            ->assertSee('class="table-wrap desktop-table-wrap"', false)
            ->assertSee('Card Type')
            ->assertSee('Guests')
            ->assertSee('RSVP')
            ->assertSee('CHANNEL')
            ->assertSee('WhatsApp Reply / Response Time')
            ->assertSee('255700009123')
            ->assertSee('.mobile-rsvp-cards { display: none; }', false)
            ->assertSee('overflow-x: auto;', false);
    }

    public function test_mobile_guest_column_is_narrow_and_whatsapp_sms_share_one_channel_column(): void
    {
        $event = $this->reportEvent('Mobile Channel Layout');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Channel Layout Guest',
            'phone' => '255700009999',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertSee('class="guest-count-column"', false)
            ->assertSee('class="mobile-combined-channel-heading"', false)
            ->assertSee('class="mobile-combined-channel"', false)
            ->assertSee('class="desktop-whatsapp-channel"', false)
            ->assertSee('class="desktop-sms-channel"', false)
            ->assertSee('class="mobile-channel-name"><svg class="channel-icon channel-icon-whatsapp" role="img" aria-label="WhatsApp"', false)
            ->assertSee('class="mobile-channel-name"><svg class="channel-icon channel-icon-sms" role="img" aria-label="SMS"', false)
            ->assertSee('width: 54px;', false)
            ->assertSee('mobile-combined-channel .delivery-status', false);
    }

    public function test_mobile_channel_is_two_compact_icon_status_rows_without_timestamps(): void
    {
        $event = $this->reportEvent('Compact Mobile Channel');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Compact Guest',
            'phone' => '255700000100',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('class="mobile-channel-entry"', false)
            ->assertSee('class="channel-icon channel-icon-whatsapp"', false)
            ->assertSee('class="channel-icon channel-icon-sms"', false)
            ->assertSee('.mobile-channel-entry {', false)
            ->assertSee('display: flex;', false)
            ->assertSee('.mobile-combined-channel .subtle {', false)
            ->assertSee('display: none;', false)
            ->assertSee('.mobile-channel-entry + .mobile-channel-entry {', false);
    }

    public function test_mobile_hides_guest_column_and_shows_both_channel_delivery_timestamps(): void
    {
        $event = $this->reportEvent('Mobile Guest and Channel Times');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Timed Channel Guest',
            'phone' => '255700009998',
            'rsvp_status' => 'attending',
        ]);

        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'read',
        ]);
        SmsLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'phone' => $invitee->phone,
            'sms_type' => 'invitation_card',
            'message' => 'Invitation',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('class="guest-count-column"', false)
            ->assertSee('.desktop-table-wrap .guest-count-column {', false)
            ->assertSee('display: none;', false)
            ->assertSee('.mobile-combined-channel .mobile-channel-timestamp {', false)
            ->assertSee('.mobile-combined-channel .mobile-channel-timestamp {', false)
            ->assertSee('grid-column: 2;', false)
            ->assertSee('class="mobile-combined-channel"', false);
    }

    public function test_mobile_report_uses_compact_column_widths_without_hiding_delivery_times(): void
    {
        $event = $this->reportEvent('Compact Mobile Table');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Compact Table Guest',
            'phone' => '255700001122',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('min-width: 0;', false)
            ->assertSee('padding: 5px 6px;', false)
            ->assertSee('min-width: 0;', false)
            ->assertSee('column-gap: 3px;', false)
            ->assertSee('class="mobile-channel-entry"', false)
            ->assertSee('.mobile-combined-channel .mobile-channel-timestamp {', false)
            ->assertSee('class="mobile-combined-channel"', false)
            ->assertSee('.desktop-table-wrap .guest-count-column {', false)
            ->assertSee('display: none;', false);
    }

    public function test_rsvp_status_column_is_hidden_only_on_phone_widths(): void
    {
        $event = $this->reportEvent('Mobile RSVP Column Visibility');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Column Visibility Guest',
            'phone' => '255700001133',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('<th rowspan="2" class="rsvp-status-column">RSVP</th>', false)
            ->assertSee('<td class="rsvp-status-column">', false)
            ->assertSee('.desktop-table-wrap .rsvp-status-column {', false)
            ->assertSee('display: none;', false)
            ->assertSee('class="mobile-combined-channel"', false)
            ->assertSee('Column Visibility Guest');
    }

    public function test_invitees_are_sorted_oldest_added_first_not_by_rsvp_or_name(): void
    {
        $event = $this->reportEvent('First Added First');
        $type = $this->reportCardType($event);

        $first = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'Z First',
            'phone' => '255700111001',
            'rsvp_status' => 'pending',
        ]);
        $second = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'A Second',
            'phone' => '255700111002',
            'rsvp_status' => 'attending',
        ]);
        // Use explicit timestamps because imports often share a creation time.
        $first->forceFill(['created_at' => now()->subDay()])->saveQuietly();
        $second->forceFill(['created_at' => now()])->saveQuietly();

        $response = $this->get($event->generateRsvpShareLink())->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'A Second'), strpos($html, 'Z First'));
    }

    public function test_mobile_table_is_compact_and_numbered_pagination_preserves_filters(): void
    {
        $event = $this->reportEvent('Compact Paginated Report');
        $type = $this->reportCardType($event);
        for ($i = 1; $i <= 51; $i++) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => sprintf('Attendee %03d', $i),
                'phone' => sprintf('25570010%04d', $i),
                'rsvp_status' => 'pending',
            ]);
        }
        $url = $event->generateRsvpShareLink();
        $this->get($url.'?status=pending')->assertOk()
            ->assertSee('min-width: 0;', false)
            ->assertSee('padding: 5px 6px;', false)
            ->assertSee('aria-label="Current page 1"', false)
            ->assertSee('aria-label="Page 2"', false)
            ->assertSee('status=pending&amp;page=2', false);

        $this->get($url.'?status=pending&page=2')->assertOk()
            ->assertSee('aria-label="Current page 2"', false)
            ->assertSee('aria-label="Page 1"', false)
            ->assertSee('Showing 51 to 51 of 51 results');
    }

    public function test_mobile_reply_heading_is_short_and_delivery_timestamps_are_readable(): void
    {
        $event = $this->reportEvent('Readable Mobile RSVP');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Readable Reply Guest',
            'phone' => '255700005550',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('class="desktop-reply-heading">WhatsApp Reply / Response Time</span>', false)
            ->assertSee('class="mobile-reply-heading">Reply / Time</span>', false)
            ->assertSee('.mobile-reply-heading { display: inline; }', false)
            ->assertSee('.desktop-reply-heading { display: none; }', false)
            ->assertSee('font-size: 10px;', false)
            ->assertSee('.mobile-combined-channel .mobile-channel-timestamp {', false)
            ->assertSee('class="reply-time-heading"', false);
    }

    public function test_phone_report_columns_use_intrinsic_widths_and_tighter_padding(): void
    {
        $event = $this->reportEvent('Narrow Mobile Columns');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Narrow Layout Guest',
            'phone' => '255700005551',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('width: max-content;', false)
            ->assertSee('min-width: 0;', false)
            ->assertSee('padding: 5px 6px;', false)
            ->assertSee('min-width: 0;', false)
            ->assertSee('min-width: 0;', false)
            ->assertSee('min-width: 0;', false)
            ->assertSee('Reply / Time');
    }

    public function test_mobile_invitee_card_channel_and_reply_columns_use_equal_cell_padding(): void
    {
        $event = $this->reportEvent('Uniform Mobile Columns');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Uniform Column Guest',
            'phone' => '255700005552',
            'rsvp_status' => 'attending',
        ]);

        $response = $this->get($event->generateRsvpShareLink())->assertOk();
        $response->assertSee('padding: 5px 6px;', false)
            ->assertSee('.desktop-table-wrap .reply-time-heading { min-width: 0;', false)
            ->assertSee('class="mobile-combined-channel-heading"', false)
            ->assertSee('class="mobile-combined-channel"', false)
            ->assertSee('Uniform Column Guest');
    }

    public function test_mobile_dashboard_has_compact_statistics_and_responsive_auto_filters(): void
    {
        $event = $this->reportEvent('Compact Mobile RSVP Dashboard');
        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Mobile Dashboard Guest',
            'phone' => '255700001145',
            'rsvp_status' => 'attending',
        ]);

        $this->get($event->generateRsvpShareLink())->assertOk()
            ->assertSee('class="response-heading-content"', false)
            ->assertSee('class="filter-actions"', false)
            ->assertSee('class="button apply-filters"', false)
            ->assertSee('grid-template-columns: repeat(2, minmax(0, 1fr));', false)
            ->assertSee('.stats .stat {', false)
            ->assertSee('padding: 11px 12px;', false)
            ->assertSee('.rsvp-live-filtering .filters .apply-filters {', false)
            ->assertSee('.rsvp-live-filtering .filters .filter-actions:has(#rsvp-clear-filters[hidden]) {', false)
            ->assertSee("document.documentElement.classList.add('rsvp-live-filtering')", false)
            ->assertSee("select.addEventListener('change'", false)
            ->assertSee("url.searchParams.delete('page')", false)
            ->assertSee('Mobile Dashboard Guest');
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

    public function test_shared_rsvp_search_finds_phone_numbers_by_default(): void
    {
        $event = $this->reportEvent('Phone Privacy RSVP Event');
        $event->forceFill(['rsvp_share_show_phone' => false])->save();

        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Private Phone Guest',
            'phone' => '255700001234',
        ]);

        $this->get($event->generateRsvpShareLink(showPhone: false).'?search=255700001234')
            ->assertOk()->assertSee('Private Phone Guest')
            ->assertSee('255700001234');
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
            ->assertSee('name="whatsapp_delivery"', false)
            ->assertSee('aria-label="Phone number"', false);

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
            ->assertSee('aria-label="Current page 1"', false)
            ->assertSee('Showing 1 to 50 of 51 results')
            ->assertSee('status=pending&amp;page=2', false)
            ->assertDontSee('<svg class="w-5 h-5"', false);

        $this->get($url . '?status=pending&page=2')
            ->assertOk()
            ->assertSee('aria-label="Current page 2"', false)
            ->assertSee('Showing 51 to 51 of 51 results')
            ->assertSee('rel="prev"', false)
            ->assertDontSee('<svg class="w-5 h-5"', false);
    }

    public function test_rows_per_page_selector_limits_results_and_preserves_filters(): void
    {
        $event = $this->reportEvent('Selectable Report Size');
        $type = $this->reportCardType($event);

        for ($index = 1; $index <= 53; $index++) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => sprintf('Rows Guest %03d', $index),
                'phone' => sprintf('25570004%04d', $index),
                'rsvp_status' => 'pending',
            ]);
        }

        $url = $event->generateRsvpShareLink();
        foreach ([10, 25, 50, 100] as $size) {
            $this->get($url.'?status=pending&per_page='.$size)
                ->assertOk()
                ->assertViewHas('invitees', fn ($paginator) =>
                    $paginator->perPage() === $size
                    && $paginator->count() === min($size, 53)
                    && $paginator->total() === 53
                )
                ->assertSee('id="rsvp-report-per-page"', false)
                ->assertSee('Rows per page')
                ->assertSee('value="'.$size.'" selected', false);
        }

        $this->get($url.'?status=pending&per_page=10&page=2')
            ->assertOk()
            ->assertViewHas('invitees', fn ($paginator) =>
                $paginator->currentPage() === 2
                && $paginator->perPage() === 10
                && $paginator->firstItem() === 11
            )
            ->assertSee('per_page=10&amp;page=3', false);
    }

    public function test_rows_per_page_rejects_unsupported_sizes(): void
    {
        $url = $this->reportEvent('Page Size Guard')->generateRsvpShareLink();
        foreach (['0', '1', '1000', 'all', '-10'] as $invalid) {
            $this->get($url.'?per_page='.$invalid)->assertSessionHasErrors('per_page');
        }
    }

    public function test_row_size_selector_is_present_even_when_single_page(): void
    {
        $url = $this->reportEvent('Small Report')->generateRsvpShareLink();
        $this->get($url)->assertOk()
            ->assertSee('id="rsvp-report-per-page"', false)
            ->assertSee('value="10"', false)
            ->assertSee('value="25"', false)
            ->assertSee('value="50"', false)
            ->assertSee('value="100"', false)
            ->assertSee("results.addEventListener('change'", false)
            ->assertSee("url.searchParams.delete('page')", false);
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

    public function test_shared_report_displays_phone_below_invitee_name_when_enabled(): void
    {
        $event = $this->reportEvent('Client Report With Phones');
        $event->forceFill(['rsvp_share_show_phone' => true])->save();

        Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Visible Phone Guest',
            'phone' => '255700000321',
        ]);

        $response = $this->get($event->generateRsvpShareLink())->assertOk();

        $response->assertSee('Visible Phone Guest')
            ->assertSee('255700000321')
            ->assertDontSee('<th rowspan="2">Phone</th>', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<div class="name">Visible Phone Guest<\\/div>\\s*<div class="subtle invitee-phone"[^>]*>255700000321<\\/div>/',
            $html
        );
    }

    public function test_new_shared_rsvp_link_shows_phone_by_default(): void
    {
        $event = $this->reportEvent('Default Phone Visible Report');
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Default Phone Guest',
            'phone' => '255700009876',
        ]);

        // No existing client link: show invitee numbers by default.
        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertSee('Default Phone Guest')
            ->assertSee($invitee->phone);

        $this->assertTrue((bool) $event->fresh()->rsvp_share_show_phone);
    }

    public function test_existing_shared_link_displays_phone_by_default(): void
    {
        $event = $this->reportEvent('Existing Private Client Report');
        $event->generateRsvpShareLink(showPhone: false);

        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Existing Hidden Phone Guest',
            'phone' => '255700009877',
        ]);

        $this->get($event->fresh()->rsvp_share_url)
            ->assertOk()
            ->assertSee('Existing Hidden Phone Guest')
            ->assertSee($invitee->phone);
    }

    public function test_existing_shared_report_displays_phone_below_name_after_owner_enables_it(): void
    {
        $event = $this->reportEvent('Client Report Phone Setting');
        $url = $event->generateRsvpShareLink(showPhone: false);

        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Enabled Phone Guest',
            'phone' => '255700009878',
        ]);

        $this->get($url)->assertOk()->assertSee($invitee->phone);

        // The owner can enable phone visibility without regenerating the link.
        $this->assertSame($url, $event->ensureRsvpShareLink(showPhone: true));
        $this->get($url)->assertOk()
            ->assertSee('Enabled Phone Guest')
            ->assertSee('255700009878')
            ->assertSee('class="subtle invitee-phone"', false)
            ->assertSee('name="sms_delivery"', false)
            ->assertSee('name="whatsapp_delivery"', false);
    }

    public function test_client_reply_is_html_escaped_and_phone_is_visible_by_default(): void
    {
        $event = $this->reportEvent('Private Client Report');
        $event->forceFill(['rsvp_share_show_phone' => false])->save();
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $this->reportCardType($event)->id,
            'name' => 'Safe Reply Guest',
            'phone' => '255700000099',
            'last_reply_message' => '<script>alert("injected")</script>',
        ]);

        $this->get($event->generateRsvpShareLink(showPhone: false))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("injected")</script>', false)
            ->assertSee($invitee->phone);
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

        $url = $event->generateRsvpShareLink(showPhone: false);
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
            $this->assertSame(6, count($query['bindings']));
            $this->assertStringNotContainsString("ESCAPE '\\\\'", $query['sql']);
        }
    }

    public function test_shared_report_counts_only_distinct_card_types_assigned_to_invitees(): void
    {
        $event = $this->reportEvent('Card Type Counts');
        $single = $this->reportCardType($event);
        $double = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Double',
            'allowed_people' => 2,
            'is_active' => false,
        ]);

        CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Family',
            'allowed_people' => 5,
            'is_active' => true,
        ]);
        CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Customized',
            'allowed_people' => 3,
            'is_active' => false,
        ]);

        foreach ([
            ['First Single', $single->id, 'pending'],
            ['Second Single', $single->id, 'attending'],
            ['Third Double', $double->id, 'not_attending'],
        ] as [$name, $cardTypeId, $status]) {
            Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $cardTypeId,
                'name' => $name,
                'phone' => '255700000019',
                'rsvp_status' => $status,
            ]);
        }

        $otherEvent = $this->reportEvent('Another Card Summary Event');
        Invitee::query()->create([
            'event_id' => $otherEvent->id,
            'card_type_id' => $this->reportCardType($otherEvent)->id,
            'name' => 'Outside Event',
            'phone' => '255700000020',
        ]);

        $url = $event->generateRsvpShareLink();

        foreach ([$url, $url.'?status=attending&per_page=10', $url.'?search=Second'] as $requestUrl) {
            $this->get($requestUrl)
                ->assertOk()
                ->assertViewHas('cardTypesUsed', 2)
                ->assertViewHas('cardTypeCounts', [
                    ['name' => 'Double', 'count' => 1],
                    ['name' => 'Single', 'count' => 2],
                ])
                ->assertSee('Card Types Used')
                ->assertSee('class="card-types-grid"', false)
                ->assertSee('class="card-types-summary-total" aria-label="2 card types">2</span>', false)
                ->assertSee('grid-template-columns: repeat(2, minmax(0, 1fr));', false)
                ->assertSee('@media (max-width: 330px)', false)
                ->assertSee('class="card-type-grid-name">Double</span>', false)
                ->assertSee('class="card-type-grid-name">Single</span>', false)
                ->assertDontSee('class="card-type-grid-name">Customized</span>', false)
                ->assertDontSee('class="card-type-grid-name">Family</span>', false)
                ->assertSee('class="card-type-grid-count">1</strong>', false)
                ->assertSee('class="card-type-grid-count">2</strong>', false)
                ->assertDontSee('Outside Event');
        }
    }

    public function test_card_types_used_is_zero_when_all_configured_types_are_unused(): void
    {
        $event = $this->reportEvent('Empty Card Summary');
        $this->reportCardType($event);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('cardTypesUsed', 0)
            ->assertViewHas('cardTypeCounts', [])
            ->assertSee('Card Types Used')
            ->assertSee('class="card-types-summary-total" aria-label="0 card types">0</span>', false)
            ->assertSee('No card types in use yet.');
    }

    public function test_card_types_used_is_zero_without_configured_types(): void
    {
        $event = $this->reportEvent('No Configured Types');

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('cardTypesUsed', 0)
            ->assertViewHas('cardTypeCounts', [])
            ->assertSee('Card Types Used');
    }

    public function test_stale_whatsapp_pending_claims_show_unverified_without_changing_tracking(): void
    {
        $event = $this->reportEvent('WhatsApp Queue Evidence');
        $type = $this->reportCardType($event);
        $template = \App\Models\MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'name' => 'Queue Audit',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'queue_audit',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
        ]);
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();

        $fixtures = [
            ['name' => 'Very Old Pending', 'status' => 'pending', 'age' => 3, 'expected' => 'Unverified'],
            ['name' => 'Recent Pending', 'status' => 'pending', 'age' => 0, 'expected' => 'Queued'],
            ['name' => 'Old Submitted', 'status' => 'submitted', 'age' => 3, 'expected' => 'Submitted'],
        ];

        $ids = [];
        foreach ($fixtures as $fixture) {
            $invitee = Invitee::query()->create([
                'event_id' => $event->id,
                'card_type_id' => $type->id,
                'name' => $fixture['name'],
                'phone' => '255700000999',
            ]);
            $ids[$fixture['name']] = $invitee->id;
            $at = now()->subDays($fixture['age']);
            \Illuminate\Support\Facades\DB::table('whatsapp_template_sends')->insert([
                'event_id' => $event->id,
                'invitee_id' => $invitee->id,
                'message_template_id' => $template->id,
                'phone_generation' => (int) $invitee->invitation_phone_generation,
                'status' => $fixture['status'],
                'attempts' => 1,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $url = $event->generateRsvpShareLink();
        $this->get($url.'?whatsapp_delivery=unknown')
            ->assertOk()
            ->assertSee('Very Old Pending')
            ->assertDontSee('Recent Pending')
            ->assertDontSee('Old Submitted')
            ->assertViewHas('deliveryStatuses', fn (array $statuses): bool =>
                $statuses[$ids['Very Old Pending']]['whatsapp'] === 'Unverified'
            );

        $this->get($url.'?whatsapp_delivery=queued')
            ->assertOk()
            ->assertSee('Recent Pending')
            ->assertDontSee('Very Old Pending');
        $this->get($url.'?whatsapp_delivery=submitted')
            ->assertOk()
            ->assertSee('Old Submitted')
            ->assertDontSee('Very Old Pending');

        // Display-only classification must not unlock or resend a claimed invitation.
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('whatsapp_template_sends')
            ->where('event_id', $event->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_provider_receipt_overrides_stale_whatsapp_claim(): void
    {
        $event = $this->reportEvent('Old Claim With Confirmed Receipt');
        $type = $this->reportCardType($event);
        $template = \App\Models\MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'name' => 'Receipt Audit',
            'content' => 'Invitation',
            'whatsapp_template_name' => 'receipt_audit',
            'whatsapp_language_code' => 'sw',
            'status' => 'active',
        ]);
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => 'Confirmed Despite Old Claim',
            'phone' => '255700000998',
        ]);
        \Illuminate\Support\Facades\DB::table('whatsapp_template_sends')->insert([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'message_template_id' => $template->id,
            'phone_generation' => (int) $invitee->invitation_phone_generation,
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        MessageLog::query()->create([
            'event_id' => $event->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'status' => 'delivered',
        ]);

        $this->get($event->generateRsvpShareLink())
            ->assertOk()
            ->assertViewHas('deliveryStatuses', fn (array $statuses): bool =>
                $statuses[$invitee->id]['whatsapp'] === 'Delivered'
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

    public function test_overall_not_sent_excludes_sms_delivered_and_current_whatsapp_tracking(): void
    {
        $event = $this->reportEvent('Overall delivery');
        $type = $this->reportCardType($event);
        $make = fn (string $name) => Invitee::query()->create([
            'event_id' => $event->id, 'card_type_id' => $type->id,
            'name' => $name, 'phone' => '255700000999',
        ]);
        $sms = $make('SMS only recipient');
        $wa = $make('Tracked WhatsApp recipient');
        $none = $make('Neither channel recipient');
        SmsLog::query()->create([
            'event_id' => $event->id, 'invitee_id' => $sms->id,
            'phone' => $sms->phone, 'sms_type' => 'invitation_card',
            'message' => 'Invitation', 'status' => 'delivered',
        ]);
        $template = \App\Models\MessageTemplate::query()->create([
            'event_id' => $event->id, 'channel' => 'whatsapp',
            'type' => 'invitation', 'name' => 'Invitation',
            'content' => 'Invitation', 'whatsapp_template_name' => 'event_invitation',
            'whatsapp_language_code' => 'sw', 'status' => 'active',
        ]);
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();
        \Illuminate\Support\Facades\DB::table('whatsapp_template_sends')->insert([
            'event_id' => $event->id, 'invitee_id' => $wa->id,
            'message_template_id' => $template->id, 'phone_generation' => (int) $wa->invitation_phone_generation,
            'status' => 'submitted', 'attempts' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = $event->generateRsvpShareLink();
        $this->get($url.'?overall_delivery=not_sent')->assertOk()
            ->assertSee($none->name)->assertDontSee($sms->name)->assertDontSee($wa->name);
        $this->get($url.'?whatsapp_delivery=not_sent')->assertOk()
            ->assertSee($sms->name)->assertSee($none->name)->assertDontSee($wa->name);
        $this->get($url.'?overall_delivery=delivered')->assertOk()
            ->assertSee($sms->name)->assertDontSee($none->name)->assertDontSee($wa->name);
        $this->get($url.'?whatsapp_delivery=submitted')->assertOk()
            ->assertSee($wa->name)
            ->assertViewHas('deliveryStatuses', fn (array $statuses) => $statuses[$wa->id]['whatsapp'] === 'Submitted');
    }

    public function test_current_invitation_receipts_override_submitted_tracking_without_mutating_claim(): void
    {
        $event = $this->reportEvent('Receipt precedence');
        $type = $this->reportCardType($event);
        $template = \App\Models\MessageTemplate::query()->create([
            'event_id' => $event->id, 'channel' => 'whatsapp',
            'type' => 'invitation', 'name' => 'Invitation',
            'content' => 'Invitation', 'whatsapp_template_name' => 'event_invitation',
            'whatsapp_language_code' => 'sw', 'status' => 'active',
        ]);
        $event->forceFill(['default_whatsapp_template_id' => $template->id])->save();
        $url = $event->generateRsvpShareLink();

        foreach (['sent', 'delivered', 'read', 'failed'] as $status) {
            $invitee = Invitee::query()->create([
                'event_id' => $event->id, 'card_type_id' => $type->id,
                'name' => 'Receipt '.$status, 'phone' => '255700000999',
            ]);
            \Illuminate\Support\Facades\DB::table('whatsapp_template_sends')->insert([
                'event_id' => $event->id, 'invitee_id' => $invitee->id,
                'message_template_id' => $template->id, 'phone_generation' => (int) $invitee->invitation_phone_generation,
                'status' => 'submitted', 'attempts' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            MessageLog::query()->create([
                'event_id' => $event->id, 'invitee_id' => $invitee->id,
                'channel' => 'whatsapp', 'type' => 'invitation', 'status' => $status,
            ]);
            MessageLog::query()->create([
                'event_id' => $event->id, 'invitee_id' => $invitee->id,
                'channel' => 'whatsapp', 'type' => 'reminder', 'status' => 'submitted',
            ]);
            $this->get($url.'?whatsapp_delivery='.$status)->assertOk()
                ->assertViewHas('deliveryStatuses', fn (array $statuses) => $statuses[$invitee->id]['whatsapp'] === ucfirst($status));
            $this->get($url.'?whatsapp_delivery=submitted')->assertOk()->assertDontSee($invitee->name);
        }
        $this->assertSame(4, \Illuminate\Support\Facades\DB::table('whatsapp_template_sends')
            ->where('event_id', $event->id)->where('status', 'submitted')->count());
    }
}
