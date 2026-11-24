<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use App\Services\SharedInviteePhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedInviteePhoneServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_phone_groups_are_event_scoped_and_normalized_without_merging_invitees(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Shared Phone Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $otherEvent = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Other Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $cardType = CardType::query()->create([
            'event_id' => $event->id, 'name' => 'Single', 'allowed_people' => 1, 'is_active' => true,
        ]);
        $otherCardType = CardType::query()->create([
            'event_id' => $otherEvent->id, 'name' => 'Single', 'allowed_people' => 1, 'is_active' => true,
        ]);

        $john = Invitee::query()->create([
            'event_id' => $event->id, 'card_type_id' => $cardType->id, 'name' => 'John', 'phone' => '0712345678',
        ]);
        $mary = Invitee::query()->create([
            'event_id' => $event->id, 'card_type_id' => $cardType->id, 'name' => 'Mary', 'phone' => '+255712345678',
        ]);
        Invitee::query()->create([
            'event_id' => $event->id, 'card_type_id' => $cardType->id, 'name' => 'Peter', 'phone' => '0755555555',
        ]);
        Invitee::query()->create([
            'event_id' => $otherEvent->id, 'card_type_id' => $otherCardType->id, 'name' => 'Another Guest', 'phone' => '255712345678',
        ]);

        $service = app(SharedInviteePhoneService::class);
        $summary = $service->summary($event);

        $this->assertSame(1, $summary['shared_numbers']);
        $this->assertSame(2, $summary['shared_invitees']);
        $this->assertCount(1, $service->others($john));
        $this->assertSame('Mary', $service->others($john)[0]['name']);
        $this->assertSame('John', $service->others($mary)[0]['name']);
        $this->assertSame(4, Invitee::query()->count());
    }

    public function test_shared_phone_filter_searches_names_and_formatted_numbers_and_sorts_by_group_size(): void
    {
        $service = app(SharedInviteePhoneService::class);
        $groups = [
            '255712345678' => [
                ['id' => 1, 'name' => 'John Peter'],
                ['id' => 2, 'name' => 'Mary Peter'],
            ],
            '255755123456' => [
                ['id' => 3, 'name' => 'Asha'],
                ['id' => 4, 'name' => 'Neema'],
                ['id' => 5, 'name' => 'James'],
            ],
        ];

        $this->assertSame(['255755123456', '255712345678'], array_map('strval', array_keys($service->filterGroups($groups))));
        $this->assertSame(['255712345678'], array_map('strval', array_keys($service->filterGroups($groups, 'MaRy'))));
        $this->assertSame(['255712345678'], array_map('strval', array_keys($service->filterGroups($groups, '+255 712 345 678'))));
        $this->assertSame(['255755123456'], array_map('strval', array_keys($service->filterGroups($groups, '', 3))));
        $this->assertSame(['255712345678', '255755123456'], array_map('strval', array_keys($service->filterGroups($groups, '', 2, 'phone_asc'))));
        $this->assertSame([], $service->filterGroups($groups, 'not found'));
    }

    public function test_shared_phone_service_ignores_nonmatching_and_invalid_numbers(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Unique Phone Test',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $cardType = CardType::query()->create([
            'event_id' => $event->id, 'name' => 'Single', 'allowed_people' => 1, 'is_active' => true,
        ]);

        $one = Invitee::query()->create([
            'event_id' => $event->id, 'card_type_id' => $cardType->id, 'name' => 'Only', 'phone' => '0712345678',
        ]);
        Invitee::query()->create([
            'event_id' => $event->id, 'card_type_id' => $cardType->id, 'name' => 'Different', 'phone' => '0755555555',
        ]);

        $service = app(SharedInviteePhoneService::class);
        $this->assertSame(0, $service->summary($event)['shared_numbers']);
        $this->assertSame([], $service->others($one));
    }
}
