<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\RelationManagers\InviteesRelationManager;
use Tests\TestCase;

class SharedPhoneReportViewStructureTest extends TestCase
{
    public function test_shared_phone_review_and_export_are_read_only_and_event_scoped(): void
    {
        $file = file_get_contents((new \ReflectionClass(InviteesRelationManager::class))->getFileName());

        $this->assertStringContainsString("Action::make('shared_phone_numbers')", $file);
        $this->assertStringContainsString("Action::make('export_shared_phone_numbers')", $file);
        $this->assertStringContainsString("->modalSubmitAction(false)", $file);
        $this->assertStringContainsString("->visible(fn (): bool => \\$this->canViewReportsForOwner())", $file);
        $this->assertStringContainsString("->groups(\\$this->getOwnerRecord())", $file);
        $this->assertStringContainsString("->streamDownload(", $file);
        $this->assertStringContainsString("e(\\$member['name'])", $file);
        $this->assertStringContainsString("shared_phone_search", $file);
    }
}
