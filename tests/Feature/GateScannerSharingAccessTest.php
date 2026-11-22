<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ViewEvent;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GateScannerSharingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_link_admits_selected_guests_without_login_and_stays_scoped_to_its_event(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['role' => User::ROLE_EVENT_ADMIN]);
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Group invitation',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $otherEvent = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Another event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Group',
            'allowed_people' => 4,
            'is_active' => true,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Group Guest',
            'phone' => '255700000001',
            'allowed_guests' => 4,
            'rsvp_status' => 'attending',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
        $otherCardType = CardType::query()->create([
            'event_id' => $otherEvent->id,
            'name' => 'Single',
            'allowed_people' => 1,
            'is_active' => true,
        ]);
        $otherInvitee = Invitee::query()->create([
            'event_id' => $otherEvent->id,
            'card_type_id' => $otherCardType->id,
            'name' => 'Other Guest',
            'phone' => '255700000002',
        ]);

        $event->enableGateShare();
        $url = $event->gateShareUrl();
        $event->enableGateShare();
        $this->assertSame($url, $event->fresh()->gateShareUrl());
        $this->assertMatchesRegularExpression('~/g/[a-f0-9]{24}$~', $url);
        $this->assertNull($otherEvent->gateShareUrl());

        $this->get($url)->assertOk();
        $this->get($url.'/attendees')->assertOk()->assertJsonPath('total', 1);
        $this->postJson($url.'/verify', ['search' => 'Other Guest'])->assertNotFound();
        $this->postJson($url.'/verify', ['search' => 'Group Guest'])
            ->assertOk()
            ->assertJsonPath('invitee.remaining_guests', 4)
            ->assertJsonMissingPath('invitee.short_code')
            ->assertJsonMissingPath('invitee.phone');
        $this->postJson($url.'/confirm', ['invitee_id' => $otherInvitee->id, 'guest_count' => 1])
            ->assertNotFound();
        $this->postJson($url.'/confirm', ['invitee_id' => $invitee->id, 'guest_count' => 3])
            ->assertOk()
            ->assertJsonPath('invitee.remaining_guests', 1)
            ->assertJsonMissingPath('invitee.short_code')
            ->assertJsonMissingPath('invitee.phone');
        $this->postJson($url.'/confirm', ['invitee_id' => $invitee->id, 'guest_count' => 2])
            ->assertUnprocessable();
        $this->postJson($url.'/confirm', ['invitee_id' => $invitee->id, 'guest_count' => 1])
            ->assertOk()->assertJsonPath('invitee.remaining_guests', 0);
        $this->assertSame(4, (int) $invitee->fresh()->checked_in_count);

        $event->disableGateShare();
        $this->get($url)->assertNotFound();
        $this->postJson($url.'/confirm', ['invitee_id' => $invitee->id, 'guest_count' => 1])
            ->assertNotFound();
    }

    public function test_event_owner_can_create_and_disable_the_short_link_from_the_event_workspace(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['role' => User::ROLE_EVENT_ADMIN]);
        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Wedding gate',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner);
        $workspace = Livewire::test(ViewEvent::class, ['record' => $event->getRouteKey()])
            ->assertSee('Create Staff Scanner Link')
            ->call('enableGateShare')
            ->assertSee('Copy Scanner Link');

        $this->assertNotNull($event->fresh()->gateShareUrl());

        $workspace->call('disableGateShare')->assertSee('Create Staff Scanner Link');
        $this->assertNull($event->fresh()->gateShareUrl());
    }

    public function test_authenticated_scanner_links_require_login_and_event_assignment(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['role' => User::ROLE_EVENT_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_CHECK_IN_OFFICER]);
        $stranger = User::factory()->create(['role' => User::ROLE_CHECK_IN_OFFICER]);

        $event = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Wedding gate',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $otherEvent = Event::query()->create([
            'user_id' => $owner->id,
            'title' => 'Different wedding',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $event->assignedUsers()->attach($officer->id, [
            'role' => User::ROLE_CHECK_IN_OFFICER,
            'is_active' => true,
        ]);

        $scanner = route('gate.check-in.show', $event);
        $namedScanner = route('gate.check-in.named', [
            'event' => $event->id,
            'slug' => 'wedding-gate',
        ]);
        $verify = route('gate.check-in.verify', $event);
        $confirm = route('gate.check-in.confirm', $event);

        $this->get($scanner)->assertRedirect();
        $this->get($namedScanner)->assertRedirect('/admin/login');
        $this->actingAs($stranger)->get($scanner)->assertForbidden();
        $this->actingAs($stranger)->get($namedScanner)->assertForbidden();
        $this->actingAs($stranger)->postJson($verify, ['search' => 'EXAMPLE'])->assertForbidden();
        $this->actingAs($stranger)->postJson($confirm, ['invitee_id' => 123, 'guest_count' => 1])->assertForbidden();

        $this->actingAs($officer)->get($scanner)->assertOk();
        $this->actingAs($officer)->get($namedScanner)->assertOk();
        $this->actingAs($officer)->get(route('gate.check-in.named', [
            'event' => $event->id,
            'slug' => 'older-wedding-title',
        ]))->assertRedirect($namedScanner);
        $this->actingAs($officer)->get(route('gate.check-in.show', $otherEvent))->assertForbidden();

        $event->assignedUsers()->updateExistingPivot($officer->id, ['is_active' => false]);
        $this->actingAs($officer)->get($scanner)->assertForbidden();
        $this->actingAs($officer)->get($namedScanner)->assertForbidden();
    }
}
