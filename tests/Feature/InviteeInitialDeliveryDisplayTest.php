<?php

namespace Tests\Feature;

use Tests\TestCase;

class InviteeInitialDeliveryDisplayTest extends TestCase
{
    public function test_invitee_table_does_not_display_unverified_status_for_new_imports(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $view = file_get_contents(resource_path('views/filament/tables/columns/invitation-delivery-status.blade.php'));

        $this->assertStringContainsString("$whatsappLabel = $whatsapp === 'Not Sent'", $source);
        $this->assertStringContainsString("$smsLabel === 'Unverified'", $source);
        $this->assertStringContainsString("'unknown' => 'Needs Review'", $source);
        $this->assertStringContainsString("@continue(blank($channel['label'] ?? null))", $view);
        $this->assertStringNotContainsString("'unknown' => 'Unverified'", $source);
    }
}
