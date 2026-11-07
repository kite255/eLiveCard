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

        $this->assertSame(4, substr_count($menu, '->dropdown(false)'));
        $this->assertStringContainsString("->maxHeight('min(70vh, 480px)')", $menu);

        $names = [
            'edit_invitee',
            'generate_card',
            'view_generated_card',
            'open_invitee_page',
            'show_private_link',
            'send_whatsapp_invitation',
            'send_sms_invitation',
            'mark_attending',
            'mark_not_attending',
            'reset_rsvp',
            'cancel_invitee',
            'delete_invitee',
        ];

        $position = -1;
        foreach ($names as $name) {
            $next = strpos($menu, "Action::make('{$name}')");
            $this->assertNotFalse($next, "Missing action: {$name}");
            $this->assertGreaterThan($position, $next, "Action out of order: {$name}");
            $position = $next;
        }

        $this->assertStringContainsString('ViewAction::make()', $menu);
        $this->assertStringNotContainsString("Action::make('send_card_link')", $menu);
        $this->assertStringContainsString("->canDeleteInviteesForOwner()", $menu);
        $this->assertStringContainsString("->requiresConfirmation()", $menu);
    }
}
