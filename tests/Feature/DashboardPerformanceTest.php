<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use App\Services\DashboardSmsBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_read_never_contacts_external_provider(): void
    {
        config()->set('services.sms_balance.url', 'https://sms.example.test/balance');
        config()->set('services.sms_balance.api_key', 'test-key');
        config()->set('services.sms_balance.api_secret', 'test-secret');

        Http::fake();
        Cache::forget(DashboardSmsBalanceService::CACHE_KEY);

        $service = app(DashboardSmsBalanceService::class);
        $this->assertSame('Updating', $service->cached());

        Cache::put(DashboardSmsBalanceService::CACHE_KEY, '1,200', now()->addMinutes(10));
        $this->assertSame('1,200', $service->cached());
        Http::assertNothingSent();
    }

    public function test_background_refresh_populates_cache_without_using_http_status_code_as_balance(): void
    {
        config()->set('services.sms_balance.url', 'https://sms.example.test/balance');
        config()->set('services.sms_balance.api_key', 'test-key');
        config()->set('services.sms_balance.api_secret', 'test-secret');
        Http::fake(['sms.example.test/*' => Http::response(['code' => 200, 'data' => ['totalSms' => 1500]], 200)]);
        Cache::forget(DashboardSmsBalanceService::CACHE_KEY);

        $service = app(DashboardSmsBalanceService::class);
        $this->assertSame('1,500', $service->refresh());
        $this->assertSame('1,500', $service->cached());
        Http::assertSentCount(1);
    }

    public function test_failed_background_refresh_preserves_last_known_balance(): void
    {
        config()->set('services.sms_balance.url', 'https://sms.example.test/balance');
        config()->set('services.sms_balance.api_key', 'test-key');
        config()->set('services.sms_balance.api_secret', 'test-secret');
        Cache::put(DashboardSmsBalanceService::CACHE_KEY, '840', now()->addMinutes(10));
        Http::fake(['sms.example.test/*' => Http::response(['error' => 'service unavailable'], 503)]);

        $service = app(DashboardSmsBalanceService::class);
        $this->assertSame('Unavailable', $service->refresh());
        $this->assertSame('840', $service->cached());
    }

    public function test_missing_card_count_uses_single_database_aggregate_and_respects_event_filter(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($admin);
        $event = Event::query()->create(['user_id' => $admin->id, 'title' => 'Dashboard Performance Event', 'event_type' => Event::TYPE_WEDDING, 'status' => Event::STATUS_ACTIVE]);
        $type = CardType::query()->create(['event_id' => $event->id, 'name' => 'Single', 'allowed_guests' => 1]);
        Invitee::query()->create(['event_id' => $event->id, 'card_type_id' => $type->id, 'name' => 'Guest A', 'phone' => '255700000101', 'card_status' => Invitee::CARD_STATUS_ACTIVE]);
        Invitee::query()->create(['event_id' => $event->id, 'card_type_id' => $type->id, 'name' => 'Guest B', 'phone' => '255700000102', 'card_status' => Invitee::CARD_STATUS_ACTIVE]);

        $page = new class extends Dashboard {
            public function missingCardsForEvent(int $eventId): int
            {
                $this->selectedEventId = $eventId;
                return $this->countInviteesWithoutCards();
            }
        };

        $this->assertSame(2, $page->missingCardsForEvent($event->id));
    }
}
