<?php

namespace Tests\Feature;

use App\Filament\Resources\SmsLogResource;
use App\Models\Event;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsDeliveryReceiptRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_refresh_uses_configured_endpoint_and_distinguishes_submission_from_delivery(): void
    {
        $log = $this->sentSms();
        $url = 'https://sms.example.test/api/v1/vendor/message/deliver/test-shoot';
        Http::fake([$url => Http::sequence()
            ->push(['data' => [['status' => 'Operator Submitted', 'statusCode' => 'ACK', 'mobile' => $log->phone]]])
            ->push(['data' => [['status' => 'Delivered', 'mobile' => $log->phone, 'sentAt' => '2026-11-22 16:32:00', 'deliveredAt' => '2026-11-22 16:45:00']]])
            ->push(['message' => 'Report unavailable'], 404)]);

        SmsLogResource::refreshDeliveryReport($log, notify: false);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('Operator Submitted', $log->fresh()->provider_status);
        $this->assertNull($log->fresh()->delivered_at);

        SmsLogResource::refreshDeliveryReport($log, notify: false);
        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame('16:45:00', $log->fresh()->delivered_at?->format('H:i:s'));

        SmsLogResource::refreshDeliveryReport($log, notify: false);
        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame('Delivered', $log->fresh()->provider_status);
        Http::assertSentCount(3);
    }

    public function test_buffered_receipt_is_pending_and_remains_eligible_for_future_delivery_checks(): void
    {
        $log = $this->sentSms();

        Http::fake(['*/deliver/test-shoot' => Http::sequence()
            ->push(['data' => [[
                'status' => 'Buffered',
                'statusCode' => 'ACK',
                'mobile' => $log->phone,
            ]]])
            ->push(['data' => [[
                'status' => 'Delivered',
                'statusCode' => '000',
                'mobile' => $log->phone,
            ]]])]);

        $service = app(\App\Services\SmsService::class);
        $service->refreshDeliveryReport('test-shoot');

        $this->assertSame('pending', $log->fresh()->status);
        $this->assertSame('Buffered', $log->fresh()->provider_status);
        $this->assertNull($log->fresh()->delivered_at);

        $invitee = \App\Models\Invitee::query()->create([
            'event_id' => $log->event_id,
            'card_type_id' => \App\Models\CardType::query()->create([
                'event_id' => $log->event_id,
                'name' => 'Single',
                'allowed_guests' => 1,
            ])->id,
            'name' => 'Buffered Guest',
            'phone' => $log->phone,
        ]);

        $log->update(['invitee_id' => $invitee->id]);
        $this->assertSame('Pending', app(\App\Services\InvitationSmsDeliveryStatusService::class)->forInvitee($invitee)['label']);

        $service->refreshDeliveryReport('test-shoot');
        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->delivered_at);
        $this->assertSame('Delivered', app(\App\Services\InvitationSmsDeliveryStatusService::class)->forInvitee($invitee)['label']);
        Http::assertSentCount(2);
    }

    public function test_a_success_envelope_without_a_message_receipt_does_not_confirm_delivery(): void
    {
        $log = $this->sentSms();
        Http::fake(['*/deliver/test-shoot' => Http::response(['status' => 'success', 'data' => []])]);

        SmsLogResource::refreshDeliveryReport($log, notify: false);

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNull($log->fresh()->delivered_at);
        $this->assertNotNull($log->fresh()->delivery_report_checked_at);
    }

    public function test_sync_checks_unqueried_receipts_before_retrying_older_messages(): void
    {
        $oldLog = $this->sentSms();
        $oldLog->update(['delivery_report_checked_at' => now()->subMinutes(10)]);
        $newLog = $oldLog->replicate();
        $newLog->fill(['provider_message_id' => 'new-shoot', 'delivery_report_checked_at' => null])->save();

        Http::fake(['*/deliver/new-shoot' => Http::response(['data' => [[
            'status' => 'Failed',
            'mobile' => $newLog->phone,
            'explanation' => 'Absent Subscriber For SM',
        ]]])]);

        $this->artisan('sms:sync-delivery-reports', ['--limit' => 1])->assertSuccessful();
        $this->assertSame('sent', $oldLog->fresh()->status);
        $this->assertSame('failed', $newLog->fresh()->status);
        $this->assertSame('Absent Subscriber For SM', $newLog->fresh()->error_message);
        Http::assertSentCount(1);
    }

    public function test_delivery_receipt_matches_unique_shoot_id_despite_phone_format_difference(): void
    {
        $log = $this->sentSms();
        $log->update(['phone' => '+255 700 000 001']);

        Http::fake(['*/deliver/test-shoot' => Http::response([
            'data' => [[
                'shootId' => 'test-shoot',
                'mobile' => '0700000001',
                'status' => 'Delivered',
                'statusCode' => '000',
                'deliveryTime' => '24-11-2026 17:36',
            ]],
        ])]);

        $result = app(\App\Services\SmsService::class)->refreshDeliveryReport('test-shoot');

        $this->assertTrue($result['success']);
        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame('Delivered', $log->fresh()->provider_status);
        $this->assertNotNull($log->fresh()->delivered_at);
    }

    public function test_single_recipient_receipt_is_reconciled_even_when_provider_phone_differs(): void
    {
        $log = $this->sentSms();
        Http::fake(['*/deliver/test-shoot' => Http::response([
            'data' => [['status' => 'Delivered', 'mobile' => '255799999999']],
        ])]);

        app(\App\Services\SmsService::class)->refreshDeliveryReport('test-shoot');

        // Shoot IDs uniquely identify a send, so provider phone formatting or
        // an inconsistent phone field must not hide a confirmed receipt.
        $this->assertSame('delivered', $log->fresh()->status);
    }

    public function test_multi_recipient_shoot_id_only_updates_matching_phone(): void
    {
        $first = $this->sentSms();
        $second = $first->replicate();
        $second->fill(['phone' => '+255700000002', 'status' => 'sent'])->save();

        Http::fake(['*/deliver/test-shoot' => Http::response([
            'data' => [['status' => 'Delivered', 'mobile' => '0700000002']],
        ])]);

        app(\App\Services\SmsService::class)->refreshDeliveryReport('test-shoot');

        $this->assertSame('sent', $first->fresh()->status);
        $this->assertSame('delivered', $second->fresh()->status);
    }

    public function test_delayed_submitted_receipt_does_not_erase_confirmed_delivery(): void
    {
        $log = $this->sentSms();
        Http::fake(['*/deliver/test-shoot' => Http::sequence()
            ->push(['data' => [['status' => 'Delivered', 'mobile' => $log->phone]]])
            ->push(['data' => [['status' => 'Operator Submitted', 'statusCode' => 'ACK', 'mobile' => $log->phone]]])]);

        $service = app(\App\Services\SmsService::class);
        $service->refreshDeliveryReport('test-shoot');
        $this->assertSame('delivered', $log->fresh()->status);

        $service->refreshDeliveryReport('test-shoot');
        $this->assertSame('delivered', $log->fresh()->status);
        $this->assertSame('Delivered', $log->fresh()->provider_status);
        $this->assertNotNull($log->fresh()->delivered_at);
    }

    public function test_adaptive_sync_checks_due_messages_and_skips_recently_checked_ones(): void
    {
        $seed = $this->sentSms();
        $seed->update([
            'sent_at' => now()->subMinutes(3),
            'delivery_report_checked_at' => now()->subMinutes(2),
        ]);

        $recentNotDue = $seed->replicate();
        $recentNotDue->fill([
            'provider_message_id' => 'recent-not-due',
            'sent_at' => now()->subMinutes(3),
            'delivery_report_checked_at' => now()->subSeconds(30),
        ])->save();

        $middleDue = $seed->replicate();
        $middleDue->fill([
            'provider_message_id' => 'middle-due',
            'sent_at' => now()->subMinutes(30),
            'delivery_report_checked_at' => now()->subMinutes(6),
        ])->save();

        $middleNotDue = $seed->replicate();
        $middleNotDue->fill([
            'provider_message_id' => 'middle-not-due',
            'sent_at' => now()->subMinutes(30),
            'delivery_report_checked_at' => now()->subMinutes(3),
        ])->save();

        $oldDue = $seed->replicate();
        $oldDue->fill([
            'provider_message_id' => 'old-due',
            'sent_at' => now()->subHours(2),
            'delivery_report_checked_at' => now()->subMinutes(31),
        ])->save();

        $oldNotDue = $seed->replicate();
        $oldNotDue->fill([
            'provider_message_id' => 'old-not-due',
            'sent_at' => now()->subHours(2),
            'delivery_report_checked_at' => now()->subMinutes(10),
        ])->save();

        Http::fake(['*/deliver/*' => Http::response(['data' => [
            ['status' => 'Delivered', 'mobile' => '255700000001'],
        ]])]);

        $this->artisan('sms:sync-delivery-reports', ['--limit' => 25])->assertSuccessful();

        $this->assertSame('delivered', $seed->fresh()->status);
        $this->assertSame('delivered', $middleDue->fresh()->status);
        $this->assertSame('delivered', $oldDue->fresh()->status);
        $this->assertSame('sent', $recentNotDue->fresh()->status);
        $this->assertSame('sent', $middleNotDue->fresh()->status);
        $this->assertSame('sent', $oldNotDue->fresh()->status);
        Http::assertSentCount(3);
    }

    public function test_explicit_shoot_id_can_be_refreshed_without_waiting_for_retry_window(): void
    {
        $log = $this->sentSms();
        $log->update(['delivery_report_checked_at' => now()]);

        Http::fake(['*/deliver/test-shoot' => Http::response([
            'data' => [['status' => 'Delivered', 'mobile' => $log->phone]],
        ])]);

        $this->artisan('sms:sync-delivery-reports', [
            '--shoot-id' => 'test-shoot',
            '--limit' => 1,
        ])->assertSuccessful();

        $this->assertSame('delivered', $log->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_scheduled_unavailable_receipts_are_audited_only_on_first_transition(): void
    {
        $log = $this->sentSms();
        Http::fake(['*/deliver/test-shoot' => Http::response(['message' => 'Report unavailable'], 404)]);

        $this->artisan('sms:sync-delivery-reports')->assertExitCode(1);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('delivery_report_unavailable', $log->fresh()->provider_status);
        $this->assertNotNull($log->fresh()->delivery_report_checked_at);

        $this->travel(31)->minutes();
        $this->artisan('sms:sync-delivery-reports')->assertExitCode(1);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('audit_logs')->where('action', 'sms.delivery_report_requested')->count());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_logs')->where('action', 'sms.delivery_report_unavailable')->count());
        Http::assertSentCount(2);

        $this->artisan('sms:sync-delivery-reports', ['--shoot-id' => 'test-shoot'])->assertExitCode(1);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_logs')->where('action', 'sms.delivery_report_requested')->count());
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('audit_logs')->where('action', 'sms.delivery_report_unavailable')->count());
        Http::assertSentCount(3);
    }

    public function test_older_receipts_back_off_and_explicit_refresh_bypasses_age_limit(): void
    {
        $seed = $this->sentSms();
        $seed->update(['sent_at' => now()->subDays(2), 'delivery_report_checked_at' => now()->subMinutes(121)]);
        $cases = [
            ['two-days-not-due', 2, 119, false],
            ['five-days-due', 5, 361, true],
            ['five-days-not-due', 5, 359, false],
            ['ten-days-due', 10, 1441, true],
            ['ten-days-not-due', 10, 1439, false],
            ['expired-window', 31, null, false],
        ];
        $records = [];
        foreach ($cases as [$id, $days, $minutes, $due]) {
            $record = $seed->replicate();
            $record->fill([
                'provider_message_id' => $id,
                'sent_at' => now()->subDays($days),
                'delivery_report_checked_at' => $minutes === null ? null : now()->subMinutes($minutes),
            ])->save();
            $records[] = [$record, $due];
        }

        Http::fake(['*/deliver/*' => Http::response(['data' => [
            ['status' => 'Delivered', 'mobile' => '255700000001'],
        ]])]);

        $this->artisan('sms:sync-delivery-reports')->assertSuccessful();
        $this->assertSame('delivered', $seed->fresh()->status);
        foreach ($records as [$record, $due]) {
            $this->assertSame($due ? 'delivered' : 'sent', $record->fresh()->status);
        }
        Http::assertSentCount(3);

        $this->artisan('sms:sync-delivery-reports', ['--shoot-id' => 'expired-window'])->assertSuccessful();
        $this->assertSame('delivered', $records[5][0]->fresh()->status);
        Http::assertSentCount(4);
    }

    public function test_sync_requests_a_shared_provider_shoot_id_only_once_per_run(): void
    {
        $first = $this->sentSms();
        $second = $first->replicate();
        $second->fill(['phone' => '255700000002'])->save();

        Http::fake(['*/deliver/test-shoot' => Http::response(['data' => [
            ['status' => 'Delivered', 'mobile' => $first->phone],
            ['status' => 'Delivered', 'mobile' => $second->phone],
        ]])]);

        $this->artisan('sms:sync-delivery-reports')->assertSuccessful();
        $this->assertSame('delivered', $first->fresh()->status);
        $this->assertSame('delivered', $second->fresh()->status);
        Http::assertSentCount(1);
    }

    private function sentSms(): SmsLog
    {
        config([
            'services.sms.base_url' => 'https://sms.example.test',
            'services.elive_sms.base_url' => 'https://sms.example.test/api/v1/vendor/message',
            'services.elive_sms.api_key' => 'test-key',
            'services.elive_sms.api_secret' => 'test-secret',
            'services.elive_sms.delivery_report_path' => '/deliver/{shootId}',
        ]);
        Http::preventStrayRequests();

        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'SMS Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        return SmsLog::query()->create([
            'event_id' => $event->id,
            'phone' => '255700000001',
            'sms_type' => SmsLog::TYPE_INVITATION,
            'message' => 'Invitation',
            'status' => SmsLog::STATUS_SENT,
            'provider_message_id' => 'test-shoot',
        ]);
    }
}
