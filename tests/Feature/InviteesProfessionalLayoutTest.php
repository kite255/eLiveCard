<?php

namespace Tests\Feature;

use Tests\TestCase;

class InviteesProfessionalLayoutTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(
            app_path('Filament/Resources/EventResource/RelationManagers/InviteesRelationManager.php')
        );
    }

    public function test_invitee_list_prioritizes_generated_card_status_and_hides_secondary_columns_by_default(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("->label('Generated Card Status')", $source);
        $this->assertStringContainsString("->label('Serial Number')", $source);
        $this->assertStringContainsString("->searchPlaceholder('Search name, phone, serial number or card type')", $source);
        $this->assertStringContainsString("->defaultPaginationPageOption(25)", $source);
        $this->assertStringContainsString(
            "eventHasInviteeColumnValue('table_number')",
            $source,
        );

        $serialStart = strpos($source, "TextColumn::make('serial_number')");
        $serialEnd = strpos($source, "IconColumn::make('qr_ready')", $serialStart);
        $this->assertNotFalse($serialStart);
        $this->assertNotFalse($serialEnd);
        $this->assertStringContainsString(
            '->toggleable(isToggledHiddenByDefault: true)',
            substr($source, $serialStart, $serialEnd - $serialStart)
        );

        $cardStart = strpos($source, "TextColumn::make('card_status')");
        $generationStart = strpos($source, "TextColumn::make('latestGeneratedCard.status')", $cardStart);
        $this->assertNotFalse($cardStart);
        $this->assertNotFalse($generationStart);
        $this->assertStringContainsString(
            '->toggleable(isToggledHiddenByDefault: true)',
            substr($source, $cardStart, $generationStart - $cardStart)
        );
    }

    public function test_table_toolbar_wraps_on_desktop_and_preserves_elive_brand_colors(): void
    {
        $theme = (string) file_get_contents(
            resource_path('css/filament/admin/theme.css')
        );

        $this->assertStringContainsString('--elive-blue: #213b73', $theme);
        $this->assertStringContainsString('--elive-orange: #fd9618', $theme);
        $this->assertStringContainsString('.fi-ta-header-actions {', $theme);
        $this->assertStringContainsString('flex-wrap: wrap;', $theme);
        $this->assertStringContainsString('max-width: 100%;', $theme);
        $this->assertStringContainsString('@media (max-width: 1100px)', $theme);
        $this->assertStringContainsString('flex-basis: 100%;', $theme);
    }

    public function test_all_messaging_toolbar_actions_remain_visible_and_card_generation_moves_to_more_actions(): void
    {
        $source = $this->source();

        $headerStart = strpos($source, '->headerActions([');
        $rowActionsStart = strpos($source, '->actionsPosition(', $headerStart);
        $this->assertNotFalse($headerStart);
        $this->assertNotFalse($rowActionsStart);
        $toolbar = substr($source, $headerStart, $rowActionsStart - $headerStart);

        foreach (['send_message', 'send_whatsapp', 'send_reminder', 'thank_you_message'] as $name) {
            $this->assertStringContainsString("Action::make('{$name}')", $toolbar);
        }

        $generatePosition = strpos($toolbar, "Action::make('generate_missing_cards')");
        $moreActionsPosition = strpos($toolbar, "Action::make('shared_phone_numbers')");
        $this->assertNotFalse($generatePosition);
        $this->assertNotFalse($moreActionsPosition);
        $this->assertGreaterThan($moreActionsPosition, $generatePosition);
        $this->assertStringContainsString("->label('More Actions')", $toolbar);
    }
}
