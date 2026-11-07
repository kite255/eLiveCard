<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventSection;
use App\Filament\Resources\EventResource\RelationManagers\InviteesRelationManager;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InviteesLivewireMenuSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitees_relation_manager_renders_and_opens_invitation_modals(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        $event = Event::query()->create([
            'user_id' => $user->id,
            'title' => 'Invitee UI Smoke Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
        $cardType = CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Single',
            'allowed_guests' => 1,
        ]);
        $invitee = Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $cardType->id,
            'name' => 'Test Invitee',
            'phone' => '255700000010',
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);

        $this->actingAs($user);

        Livewire::test(InviteesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ManageEventSection::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$invitee])
            ->mountTableAction('send_sms_invitation', $invitee)
            ->assertHasNoTableActionErrors();

        Livewire::test(InviteesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ManageEventSection::class,
        ])
            ->assertSuccessful()
            ->mountTableAction('send_whatsapp_invitation', $invitee)
            ->assertHasNoTableActionErrors();

        Livewire::test(InviteesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ManageEventSection::class,
        ])
            ->assertSuccessful()
            ->mountTableAction('send_message')
            ->assertHasNoTableActionErrors()
            ->assertSee('Recipient Review');
    }
}
