<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OvernightEventCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_midnight_end_time_means_next_day_for_an_evening_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 19:00:00', 'Africa/Dar_es_Salaam'));

        try {
            $event = Event::query()->create([
                'user_id' => User::factory()->create()->id,
                'title' => 'Evening wedding',
                'event_type' => Event::TYPE_WEDDING,
                'event_date' => '2026-11-08',
                'start_time' => '18:00',
                'end_time' => '00:00',
                'status' => Event::STATUS_ACTIVE,
            ]);

            $this->assertSame('2026-11-09 00:00', $event->ends_at->format('Y-m-d H:i'));
            $this->assertSame(Event::STATUS_ACTIVE, $event->display_status);

            $this->artisan('events:complete-finished')->assertExitCode(0);
            $this->assertSame(Event::STATUS_ACTIVE, $event->fresh()->status);

            Carbon::setTestNow(Carbon::parse('2026-11-09 00:01:00', 'Africa/Dar_es_Salaam'));
            $this->artisan('events:complete-finished')->assertExitCode(0);
            $this->assertSame(Event::STATUS_COMPLETED, $event->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }
}
