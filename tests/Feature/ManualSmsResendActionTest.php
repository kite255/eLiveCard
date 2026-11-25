<?php

namespace Tests\Feature;

use Tests\TestCase;

class ManualSmsResendActionTest extends TestCase
{
    public function test_sms_resend_requires_only_confirmation_and_preserves_audit_reason(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $start = strpos($source, "return Tables\\Actions\\Action::make('force_resend_'.\$channel.'_invitation')");
        $end = strpos($source, 'protected function processInviteeInvitation(', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $smsAction = substr($source, $start, $end - $start);

        $this->assertStringContainsString("->modalHeading('Resend SMS Invitation')", $smsAction);
        $this->assertStringContainsString("->modalSubmitActionLabel('Resend SMS')", $smsAction);
        $this->assertStringContainsString("->modalCancelActionLabel('Cancel')", $smsAction);
        $this->assertStringContainsString('->requiresConfirmation()', $smsAction);
        $this->assertStringNotContainsString("Textarea::make('resend_reason')", $smsAction);
        $this->assertStringNotContainsString("Checkbox::make('confirm_duplicate')", $smsAction);
        $this->assertStringNotContainsString('->form([', $smsAction);
        $this->assertStringContainsString('Manual SMS resend confirmed by administrator.', $smsAction);
        $this->assertStringContainsString('processInviteeInvitation(', $smsAction);
    }

    public function test_no_automatic_resend_for_submitted_sms(): void
    {
        $source = file_get_contents(app_path('Services/SmsInvitationRecipientsService.php'));
        $this->assertStringContainsString("'sent', 'delivered', 'accepted', 'submitted', 'logged', 'queued'", $source);
        $this->assertStringContainsString('exclusionReason(Invitee $invitee)', $source);
    }
}
