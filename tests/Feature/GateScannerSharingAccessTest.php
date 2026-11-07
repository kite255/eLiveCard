<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateScannerSharingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_scanner_link_requires_login_and_event_assignment(): void
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
