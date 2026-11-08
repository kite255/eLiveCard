<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Filament\Resources\EventResource\Pages\ListEvents;
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
            $this->assertTrue(Event::query()->active()->whereKey($event->id)->exists());
            $this->assertFalse(Event::query()->completed()->whereKey($event->id)->exists());

            $this->artisan('events:complete-finished')->assertExitCode(0);
            $this->assertSame(Event::STATUS_ACTIVE, $event->fresh()->status);

            Carbon::setTestNow(Carbon::parse('2026-11-09 00:01:00', 'Africa/Dar_es_Salaam'));
            $this->artisan('events:complete-finished')->assertExitCode(0);
            $this->assertSame(Event::STATUS_COMPLETED, $event->fresh()->status);
            $this->assertTrue(Event::query()->completed()->whereKey($event->id)->exists());
        } finally {
            Carbon::setTestNow();
        }
    }
    public function test_events_list_does_not_classify_overnight_event_as_completed_before_it_starts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 00:37:00', 'Africa/Dar_es_Salaam'));

        try {
            $event = Event::query()->create([
                'user_id' => User::factory()->create()->id,
                'title' => 'Evening wedding event list',
                'event_type' => Event::TYPE_WEDDING,
                'event_date' => '2026-11-08',
                'start_time' => '18:00',
                'end_time' => '00:00',
                'status' => Event::STATUS_ACTIVE,
            ]);

            $presentation = (new ListEvents())->eventPresentation($event);

            $this->assertSame('upcoming', $presentation['classification']);
            $this->assertSame('Upcoming', $presentation['label']);
            $this->assertSame(Event::STATUS_ACTIVE, $event->fresh()->status);

            Carbon::setTestNow(Carbon::parse('2026-11-08 20:00:00', 'Africa/Dar_es_Salaam'));
            $this->assertSame('active', (new ListEvents())->eventPresentation($event)['classification']);

            Carbon::setTestNow(Carbon::parse('2026-11-09 00:01:00', 'Africa/Dar_es_Salaam'));
            $this->assertSame('completed', (new ListEvents())->eventPresentation($event)['classification']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_premature_completion_repair_requires_explicit_apply_and_event_id(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 02:50:00', 'Africa/Dar_es_Salaam'));

        try {
            $event = Event::query()->create([
                'user_id' => User::factory()->create()->id,
                'title' => 'Evening wedding incorrectly completed',
                'event_type' => Event::TYPE_WEDDING,
                'event_date' => '2026-11-08',
                'start_time' => '18:00',
                'end_time' => '00:00',
                'status' => Event::STATUS_COMPLETED,
            ]);

            $this->artisan('events:repair-premature-completion', [
                '--event' => $event->id,
            ])->assertExitCode(0);

            $this->assertSame(Event::STATUS_COMPLETED, $event->fresh()->status);

            $this->artisan('events:repair-premature-completion', [
                '--apply' => true,
            ])->assertExitCode(1);

            $this->assertSame(Event::STATUS_COMPLETED, $event->fresh()->status);

            $this->artisan('events:repair-premature-completion', [
                '--event' => $event->id,
                '--apply' => true,
            ])->assertExitCode(0);

            $this->assertSame(Event::STATUS_ACTIVE, $event->fresh()->status);
            $this->assertSame(Event::STATUS_UPCOMING, $event->fresh()->display_status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_repair_never_reopens_an_event_after_its_real_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 00:01:00', 'Africa/Dar_es_Salaam'));

        try {
            $event = Event::query()->create([
                'user_id' => User::factory()->create()->id,
                'title' => 'Completed evening wedding',
                'event_type' => Event::TYPE_WEDDING,
                'event_date' => '2026-11-08',
                'start_time' => '18:00',
                'end_time' => '00:00',
                'status' => Event::STATUS_COMPLETED,
            ]);

            $this->artisan('events:repair-premature-completion', [
                '--event' => $event->id,
                '--apply' => true,
            ])->assertExitCode(0);

            $this->assertSame(Event::STATUS_COMPLETED, $event->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

}
