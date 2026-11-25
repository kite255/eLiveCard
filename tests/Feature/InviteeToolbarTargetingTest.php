<?php

namespace Tests\Feature;

use App\Exports\EventInviteesExport;
use App\Filament\Resources\EventResource\Pages\ManageEventSection;
use App\Filament\Resources\EventResource\RelationManagers\InviteesRelationManager;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Jobs\SendInvitationSmsJob;
use App\Services\SmsInvitationRecipientsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class InviteeToolbarTargetingTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $user): Event
    {
        return Event::query()->create([
            'user_id' => $user->id,
            'title' => 'Targeting regression',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function invitee(Event $event, string $name, string $phone): Invitee
    {
        $type = CardType::query()->firstOrCreate([
            'event_id' => $event->id,
            'name' => 'Single',
        ], ['allowed_guests' => 1]);

        return Invitee::query()->create([
            'event_id' => $event->id,
            'card_type_id' => $type->id,
            'name' => $name,
            'phone' => $phone,
            'card_status' => Invitee::CARD_STATUS_ACTIVE,
        ]);
    }

    private function inviteeTableComponent(Event $event)
    {
        return Livewire::test(InviteesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => ManageEventSection::class,
        ]);
    }

    private function template(Event $event): void
    {
        MessageTemplate::query()->create([
            'event_id' => $event->id,
            'channel' => 'sms',
            'type' => 'invitation',
            'name' => 'Invitation',
            'content' => 'Habari #NAME# #PRIVATE_INVITATION_URL#',
            'status' => 'active',
        ]);
    }

    public function test_export_and_sms_preview_restrict_empty_and_foreign_ids(): void
    {
        $user = User::factory()->create();
        $event = $this->event($user);
        $other = $this->event($user);
        $a = $this->invitee($event, 'Asha', '255700000011');
        $b = $this->invitee($event, 'Baraka', '255700000012');
        $foreign = $this->invitee($other, 'Foreign', '255700000013');

        $this->assertSame([$a->id], (new EventInviteesExport($event->id, [$a->id, $foreign->id]))->collection()->modelKeys());
        $this->assertCount(0, (new EventInviteesExport($event->id, []))->collection());
        $this->assertCount(2, (new EventInviteesExport($event->id))->collection());

        $service = app(SmsInvitationRecipientsService::class);
        $this->assertSame(['eligible' => 1, 'excluded' => 0], $service->counts($event, 'not_sent', [$a->id, $foreign->id]));
        $this->assertSame(['eligible' => 0, 'excluded' => 0], $service->counts($event, 'not_sent', []));
        $b->forceFill(['invitation_sms_status' => 'sent'])->save();
        $this->assertSame(['eligible' => 0, 'excluded' => 1], $service->counts($event, 'not_sent', [$b->id]));
    }

    public function test_filtered_sms_toolbar_queues_only_matching_invitees(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($user);
        $event = $this->event($user);
        $a = $this->invitee($event, 'Asha Unique', '255700000021');
        $this->invitee($event, 'Baraka', '255700000022');
        $this->invitee($this->event($user), 'Asha Unique', '255700000023');
        $this->template($event);

        $this->inviteeTableComponent($event)
            ->searchTable('Asha Unique')
            ->callTableAction('send_message', data: [
                'target_scope' => 'filtered',
                'target_confirmed' => true,
                'recipient_scope' => 'not_sent',
                'shared_phone_policy' => 'skip',
            ])
            ->assertHasNoTableActionErrors();

        Queue::assertPushed(SendInvitationSmsJob::class, 1);
        $this->assertSame(Invitee::SMS_STATUS_PENDING, $a->fresh()->invitation_sms_status);
        $this->assertSame('queued', $a->fresh()->last_message_status);
    }

    public function test_empty_selected_sms_toolbar_does_not_send_to_all(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($user);
        $event = $this->event($user);
        $this->invitee($event, 'Asha', '255700000031');
        $this->template($event);

        $this->inviteeTableComponent($event)
            ->callTableAction('send_message', data: [
                'target_scope' => 'selected',
                'target_confirmed' => true,
                'recipient_scope' => 'not_sent',
                'shared_phone_policy' => 'skip',
            ])
            ->assertHasNoTableActionErrors();

        Queue::assertNothingPushed();
    }

    public function test_page_size_changes_reset_page_and_update_record_count(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($user);
        $event = $this->event($user);
        for ($i = 1; $i <= 51; $i++) {
            $this->invitee($event, 'Guest '.$i, '25570001'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
        }

        $component = $this->inviteeTableComponent($event);
        $component->set('tableRecordsPerPage', 25);
        $this->assertSame(25, $component->instance()->getTableRecords()->perPage());
        $component->call('gotoPage', 2, $component->instance()->getTablePaginationPageName());
        $this->assertSame(2, $component->instance()->getTableRecords()->currentPage());

        $component->set('tableRecordsPerPage', '50');
        $records = $component->instance()->getTableRecords();
        $this->assertSame(50, $records->perPage());
        $this->assertSame(1, $records->currentPage());
        $this->assertSame(2, $records->lastPage());
        $this->assertCount(50, $records->items());

        $component->set('tableRecordsPerPage', 'all');
        $this->assertCount(51, $component->instance()->getTableRecords()->items());

        $component->set('tableRecordsPerPage', '999');
        $this->assertSame(25, $component->instance()->getTableRecords()->perPage());
    }
}
