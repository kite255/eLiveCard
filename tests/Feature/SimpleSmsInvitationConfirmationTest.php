<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimpleSmsInvitationConfirmationTest extends TestCase
{
    public function test_sms_toolbar_shows_four_recipient_options_and_one_count(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $start = strpos($source, "Tables\\Actions\\Action::make('send_message')");
        $end = strpos($source, "Tables\\Actions\\Action::make('send_whatsapp')", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $smsAction = substr($source, $start, $end - $start);

        $this->assertStringContainsString("->modalSubmitActionLabel('Send SMS')", $smsAction);
        $this->assertStringContainsString("->label('Send To')", $smsAction);
        $this->assertStringContainsString("->label('Eligible Recipients')", $smsAction);
        $this->assertStringContainsString("smsEligibleCount(", $smsAction);
        $this->assertStringContainsString("SCOPE_NOT_SENT", $smsAction);
        $this->assertStringContainsString("SCOPE_FAILED_WHATSAPP", $smsAction);
        $this->assertStringContainsString("SCOPE_WHATSAPP_UNCONFIRMED", $smsAction);
        $this->assertStringContainsString("SCOPE_ALL_ACTIVE", $smsAction);
        $this->assertStringNotContainsString("SCOPE_FAILED_SMS", $smsAction);
        $this->assertStringContainsString("->requiresConfirmation()", $smsAction);
        $this->assertStringContainsString("'skip'", $smsAction);
        $this->assertStringContainsString("toolbarTargetIds('filtered')", $smsAction);
        $this->assertStringContainsString("protected function smsEligibleCount(", $source);
    }
}
