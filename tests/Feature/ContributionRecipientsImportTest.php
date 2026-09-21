<?php

namespace Tests\Feature;

use App\Imports\ContributionRecipientsImport;
use App\Models\ContributionRecipient;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContributionRecipientsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_name_and_phone_for_an_event(): void
    {
        $event = $this->event();
        $import = new ContributionRecipientsImport($event->id);

        $import->collection(new Collection([
            new Collection([
                'name' => '  Kitenken Lucas  ',
                'phone' => '0754 123 456',
            ]),
        ]));

        $recipient = ContributionRecipient::query()->sole();

        $this->assertSame($event->id, $recipient->event_id);
        $this->assertSame('Kitenken Lucas', $recipient->name);
        $this->assertSame('255754123456', $recipient->phone);
        $this->assertSame(ContributionRecipient::STATUS_NOT_SENT, $recipient->whatsapp_status);
        $this->assertSame(1, $import->importedCount);
    }

    public function test_name_and_phone_are_required(): void
    {
        $event = $this->event();
        $import = new ContributionRecipientsImport($event->id);

        try {
            $import->collection(new Collection([
                new Collection(['name' => '', 'phone' => '']),
            ]));

            $this->fail('The invalid import should throw a validation exception.');
        } catch (ValidationException $exception) {
            $messages = $exception->errors()['import_file'] ?? [];

            $this->assertContains('Row 2: Name is required.', $messages);
            $this->assertContains('Row 2: Phone number is required.', $messages);
        }

        $this->assertDatabaseCount('contribution_recipients', 0);
    }

    public function test_duplicate_phone_numbers_are_not_imported_twice_for_the_same_event(): void
    {
        $event = $this->event();
        $import = new ContributionRecipientsImport($event->id);

        $this->expectException(ValidationException::class);

        try {
            $import->collection(new Collection([
                new Collection(['name' => 'First Member', 'phone' => '0754123456']),
                new Collection(['name' => 'Second Member', 'phone' => '+255754123456']),
            ]));
        } finally {
            $this->assertDatabaseCount('contribution_recipients', 1);
        }
    }

    private function event(): Event
    {
        $user = User::factory()->create();

        return Event::query()->create([
            'user_id' => $user->id,
            'title' => 'Contribution Event',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);
    }
}
