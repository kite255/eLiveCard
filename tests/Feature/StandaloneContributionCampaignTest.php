<?php

namespace Tests\Feature;

use App\Models\ContributionCampaign;
use App\Models\ContributionRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandaloneContributionCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_campaign_can_have_members_without_an_event(): void
    {
        $owner = User::factory()->create();

        $campaign = ContributionCampaign::query()->create([
            'created_by' => $owner->id,
            'event_id' => null,
            'campaign_type' => 'church',
            'name' => 'Church Building Fund',
            'description' => 'Building contribution campaign',
            'template_image' => 'contribution-campaigns/templates/sample.jpg',
        ]);

        $recipient = $campaign->recipients()->create([
            'event_id' => null,
            'name' => 'Sample Member',
            'phone' => '255754123456',
        ]);

        $this->assertNull($campaign->fresh()->event_id);
        $this->assertSame($owner->id, $campaign->fresh()->created_by);
        $this->assertSame('church', $campaign->fresh()->campaign_type);
        $this->assertNull($recipient->fresh()->event_id);
        $this->assertInstanceOf(ContributionRecipient::class, $campaign->recipients()->first());
    }

    public function test_campaign_statuses_support_draft_paused_and_completed(): void
    {
        $statuses = ContributionCampaign::statuses();

        $this->assertSame('Draft', $statuses['draft']);
        $this->assertSame('Paused', $statuses['paused']);
        $this->assertSame('Completed', $statuses['completed']);
    }
}
