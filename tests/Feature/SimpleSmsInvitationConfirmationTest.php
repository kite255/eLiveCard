<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleSmsInvitationConfirmationTest extends TestCase
{
    public function test_sms_toolbar_uses_single_confirmation_and_existing_safe_sender(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $start = strpos($source, "Tables\\Actions\\Action::make('send_message')");
        $end = strpos($source, "Tables\\Actions\\Action::make('send_whatsapp')", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $smsAction = substr($source, $start, $end - $start);

        $this->assertStringContainsString("->modalSubmitActionLabel('Confirm & Send')", $smsAction);
        $this->assertStringContainsString("smsUnsentCount(\$this->toolbarTargetIds('filtered'))", $smsAction);
        $this->assertStringContainsString("SCOPE_NOT_SENT", $smsAction);
        $this->assertStringContainsString("'skip'", $smsAction);
        $this->assertStringContainsString("toolbarTargetIds('filtered')", $smsAction);
        $this->assertStringNotContainsString("->form([", $smsAction);
        $this->assertStringContainsString("protected function smsUnsentCount(", $source);
        $this->assertStringContainsString("Shared phone numbers are excluded for manual review.", $smsAction);
    }
}
