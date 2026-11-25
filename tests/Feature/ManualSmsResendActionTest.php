<?php

namespace Tests\Feature;

use Tests\TestCase;

class ManualSmsResendActionTest extends TestCase
{
    public function test_sms_resend_requires_reason_and_duplicate_acknowledgement(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $start = strpos($source, "return Tables\\Actions\\Action::make('force_resend_'.\$channel.'_invitation')");
        $end = strpos($source, 'protected function processInviteeInvitation(', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $smsAction = substr($source, $start, $end - $start);

        $this->assertStringContainsString("Textarea::make('resend_reason')", $smsAction);
        $this->assertStringContainsString("Checkbox::make('confirm_duplicate')", $smsAction);
        $this->assertStringContainsString('->minLength(10)', $smsAction);
        $this->assertStringContainsString('->maxLength(500)', $smsAction);
        $this->assertStringContainsString('->accepted()', $smsAction);
        $this->assertStringContainsString("['confirm_duplicate']", $smsAction);
        $this->assertStringContainsString("['resend_reason']", $smsAction);
        $this->assertStringNotContainsString('Manual SMS invitation resend from invitee actions.', $smsAction);
        $this->assertStringContainsString('processInviteeInvitation($record, $channel, $reason)', $smsAction);
    }

    public function test_no_automatic_resend_for_submitted_sms(): void
    {
        $source = file_get_contents(app_path('Services/SmsInvitationRecipientsService.php'));
        $this->assertStringContainsString("'sent', 'delivered', 'accepted', 'submitted', 'logged', 'queued'", $source);
        $this->assertStringContainsString('exclusionReason(Invitee $invitee)', $source);
    }
}
