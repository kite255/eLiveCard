<?php

namespace Tests\Feature;

use App\Imports\InviteesImport;
use App\Models\CardType;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class InviteesImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_valid_rows_when_other_rows_have_errors(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Wedding Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Family',
            'allowed_people' => 4,
            'is_active' => true,
        ]);

        $import = new InviteesImport($event->id);
        $import->collection(new Collection([
            new Collection([
                'name' => 'Kitenken Lucas',
                'phone' => '0754 123 456',
                'card_type' => 'Family',
                'allowed_guests' => '3',
            ]),
            new Collection([
                'name' => '',
                'phone' => '',
                'card_type' => 'Family',
            ]),
        ]));

        $invitee = Invitee::query()->sole();

        $this->assertSame('Kitenken Lucas', $invitee->name);
        $this->assertSame('255754123456', $invitee->phone);
        $this->assertSame(3, $invitee->allowed_guests);
        $this->assertSame(1, $import->importedCount);
        $this->assertContains('Row 3: Name is required.', $import->errors);
    }

    public function test_it_imports_an_international_whatsapp_number_with_its_country_code(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Wedding Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        CardType::query()->create([
            'event_id' => $event->id,
            'name' => 'Family',
            'allowed_people' => 4,
            'is_active' => true,
        ]);

        $import = new InviteesImport($event->id);
        $import->collection(new Collection([
            new Collection([
                'name' => 'Kenyan Guest',
                'phone' => '+254 712 345 678',
                'card_type' => 'Family',
            ]),
        ]));

        $this->assertSame(1, $import->importedCount);
        $this->assertSame('+254712345678', Invitee::query()->sole()->phone);
    }
}
