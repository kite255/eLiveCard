<?php

namespace Tests\Feature;

use Tests\TestCase;

class InviteeActionsMenuStructureTest extends TestCase
{
    public function test_invitee_actions_are_grouped_with_dividers_and_mobile_scrolling(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php'));
        $start = strpos($source, '->actions([');
        $end = strpos($source, '->bulkActions([', $start);
        $menu = substr($source, $start, $end - $start);

        $this->assertSame(0, substr_count($menu, '->dropdown(false)'));
        $this->assertSame(2, substr_count($menu, 'Tables\\Actions\\ActionGroup::make(['));
        $this->assertStringContainsString("->label('Manage invitee')", $menu);
        $this->assertStringContainsString("->label('More invitee actions')", $menu);
        $this->assertStringContainsString('MaxWidth::Medium', $menu);
        $this->assertStringContainsString("->dropdownMaxHeight('min(70dvh, 32rem)')", $menu);
        $this->assertStringContainsString("->label('Delivery Status')", $source);
        $this->assertStringContainsString("Action::make('audit_missing_invitation_sms_logs')", $source);

        $mainStart = strpos($menu, "Tables\\Actions\\ActionGroup::make([");
        $advancedStart = strpos($menu, "Tables\\Actions\\ActionGroup::make([", $mainStart + 1);
        $this->assertNotFalse($advancedStart);
        $primary = substr($menu, $mainStart, $advancedStart - $mainStart);
        $advanced = substr($menu, $advancedStart);

        // The everyday menu contains seven clearly named actions.
        foreach ([
            'edit_invitee',
            'open_invitee_page',
            'send_whatsapp_invitation',
            'send_sms_invitation',
            'refresh_sms_delivery',
            'update_rsvp',
            'cancel_invitee',
        ] as $name) {
            $this->assertStringContainsString("Action::make('{$name}')", $primary);
            $this->assertStringNotContainsString("Action::make('{$name}')", $advanced);
        }

        foreach ([
            'generate_card',
            'view_generated_card',
            'retry_failed_card',
            'show_private_link',
            'mark_attending',
            'mark_not_attending',
            'reset_rsvp',
            'delete_invitee',
        ] as $name) {
            $this->assertStringContainsString("Action::make('{$name}')", $advanced);
            $this->assertStringNotContainsString("Action::make('{$name}')", $primary);
        }

        foreach (['CARDS & DETAILS', 'RESEND INVITATIONS', 'ADVANCED RSVP', 'DELETE'] as $heading) {
            $this->assertStringContainsString("->label('{$heading}')", $advanced);
        }
        $this->assertStringContainsString("resendInvitationAction('sms')", $advanced);
        $this->assertStringContainsString("resendInvitationAction('whatsapp')", $advanced);
        $this->assertStringContainsString("->label('Update RSVP')", $primary);
        $this->assertStringContainsString("'rsvp_confirmed_at' => ", $primary);

        $this->assertStringContainsString('ViewAction::make()', $menu);
        $this->assertStringNotContainsString("Action::make('send_card_link')", $menu);
        $this->assertStringContainsString("->canDeleteInviteesForOwner()", $menu);
        $this->assertStringContainsString("->requiresConfirmation()", $menu);
    }
}
