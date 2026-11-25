<?php

namespace Tests\Feature;

use Tests\TestCase;

class TargetedWhatsAppResendActionTest extends TestCase
{
    public function test_resend_requires_reason_and_duplicate_acknowledgement(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $start = strpos($source, "Action::make('force_resend_whatsapp_invitation')");
        $end = strpos($source, "return Tables\\Actions\\Action::make('force_resend_'.\$channel", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $action = substr($source, $start, $end - $start);
        $this->assertStringContainsString("Textarea::make('resend_reason')", $action);
        $this->assertStringContainsString("Checkbox::make('confirm_duplicate')", $action);
        $this->assertStringContainsString('->accepted()', $action);
        $this->assertStringContainsString('->minLength(10)', $action);
        $this->assertStringContainsString('->maxLength(500)', $action);
        $this->assertStringContainsString("trim((string) \$data['resend_reason'])", $action);
        $this->assertStringContainsString('Confirm Intentional Resend', $action);
        $this->assertStringContainsString('displayWhatsAppStatus($record)', $action);
    }

    public function test_existing_resend_service_checks_in_flight_and_audits_reason(): void
    {
        $source = file_get_contents(app_path('Services/InviteeInvitationService.php'));
        $this->assertStringContainsString("whereIn('sends.status', ['pending', 'sending'])", $source);
        $this->assertStringContainsString('recordResend($invitee, $channel, $resendReason)', $source);
    }
}
