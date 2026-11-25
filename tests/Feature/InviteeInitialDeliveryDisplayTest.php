<?php

namespace Tests\Feature;

use Tests\TestCase;

class InviteeInitialDeliveryDisplayTest extends TestCase
{
    public function test_new_invitees_have_no_delivery_badges_before_sending(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $view = file_get_contents(resource_path('views/filament/tables/columns/invitation-delivery-status.blade.php'));

        $this->assertStringContainsString("\$whatsappLabel = \$whatsapp === 'Not Sent'", $source);
        $this->assertStringContainsString("\$smsLabel === 'Unverified'", $source);
        $this->assertStringContainsString("'unknown' => 'Needs Review'", $source);
        $this->assertStringContainsString('@continue(blank(', $view);
        $this->assertStringNotContainsString("'unknown' => 'Unverified'", $source);
    }
}
