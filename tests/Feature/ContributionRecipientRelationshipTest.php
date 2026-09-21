<?php

namespace Tests\Feature;

use App\Models\ContributionRecipient;
use App\Models\ContributionCampaign;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionRecipientRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_owns_contribution_recipients(): void
    {
        $event = Event::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Committee Contribution',
            'event_type' => Event::TYPE_WEDDING,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaign = ContributionCampaign::query()->create([
            'event_id' => $event->id,
            'name' => 'Main campaign',
            'template_image' => 'templates/contribution.jpg',
        ]);

        ContributionRecipient::query()->create([
            'event_id' => $event->id,
            'contribution_campaign_id' => $campaign->id,
            'name' => 'Committee Member',
            'phone' => '255754123456',
        ]);

        $this->assertCount(1, $event->contributionRecipients);
        $this->assertSame('Committee Member', $event->contributionRecipients->first()->name);
    }
}
