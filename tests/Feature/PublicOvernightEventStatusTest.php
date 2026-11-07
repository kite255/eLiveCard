<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicOvernightEventStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_page_shows_upcoming_before_an_evening_event_ending_at_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 00:37:00', 'Africa/Dar_es_Salaam'));

        try {
            $event = Event::query()->create([
                'user_id' => User::factory()->create()->id,
                'title' => 'Evening Wedding',
                'event_type' => Event::TYPE_WEDDING,
                'event_date' => '2026-11-08',
                'start_time' => '18:00',
                'end_time' => '00:00',
                'status' => Event::STATUS_ACTIVE,
                'is_public' => true,
            ]);

            $this->get(route('events.show', $event))
                ->assertOk()
                ->assertSee('Evening Wedding')
                ->assertSee('Upcoming')
                ->assertDontSee('This event has been completed.');

            $this->assertSame(Event::STATUS_ACTIVE, $event->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }
}
