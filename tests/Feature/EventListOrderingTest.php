<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventListOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_are_sorted_by_display_status_then_relevant_date_before_pagination(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 19:00:00', 'Africa/Dar_es_Salaam'));

        try {
            $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
            $this->actingAs($user);
            $make = function (string $title, string $date, string $status, string $updated) use ($user): Event {
                $event = Event::query()->create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'event_type' => Event::TYPE_WEDDING,
                    'event_date' => $date,
                    'start_time' => '18:00',
                    'end_time' => '00:00',
                    'status' => $status,
                ]);
                $event->forceFill(['updated_at' => $updated])->save();
                return $event;
            };
            $old = $make('Old completed', '2026-09-01', Event::STATUS_COMPLETED, '2026-09-02');
            $far = $make('Later upcoming', '2026-12-10', Event::STATUS_ACTIVE, '2026-11-01');
            $draftOld = $make('Old draft', '2026-12-01', Event::STATUS_DRAFT, '2026-11-10');
            $active = $make('Happening', '2026-11-25', Event::STATUS_ACTIVE, '2026-11-01');
            $recent = $make('Recent completed', '2026-11-22', Event::STATUS_COMPLETED, '2026-11-23');
            $near = $make('Nearest upcoming', '2026-11-26', Event::STATUS_ACTIVE, '2026-11-01');
            $draftNew = $make('New draft', '2026-12-15', Event::STATUS_DRAFT, '2026-11-24');
            $stale = $make('Finished but stored active', '2026-11-24', Event::STATUS_ACTIVE, '2026-11-01');

            $page = new class extends ListEvents {
                public function sortedIds(): array
                {
                    return $this->getEvents($this->getStatusIds())->getCollection()->modelKeys();
                }
            };

            $this->assertSame([
                $active->id, $near->id, $far->id, $draftNew->id, $draftOld->id,
                $stale->id, $recent->id, $old->id,
            ], $page->sortedIds());

            $page->statusFilter = 'completed';
            $this->assertSame([$stale->id, $recent->id, $old->id], $page->sortedIds());
            $page->statusFilter = 'upcoming';
            $this->assertSame([$near->id, $far->id], $page->sortedIds());
        } finally {
            Carbon::setTestNow();
        }
    }
}
