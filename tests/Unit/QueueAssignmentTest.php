<?php

namespace Tests\Unit;

use App\Jobs\GenerateContributionCardJob;
use App\Jobs\GenerateInviteeCardJob;
use App\Jobs\SendContributionCardJob;
use App\Jobs\SendInvitationSmsJob;
use App\Jobs\SendInvitationWhatsAppJob;
use PHPUnit\Framework\TestCase;

class QueueAssignmentTest extends TestCase
{
    public function test_card_generation_jobs_use_the_card_generation_queue(): void
    {
        $this->assertSame(
            'card-generation',
            (new GenerateContributionCardJob(1))->queue
        );

        $this->assertSame(
            'card-generation',
            (new GenerateInviteeCardJob(1))->queue
        );
    }

    public function test_contribution_delivery_uses_the_communications_queue(): void
    {
        $this->assertSame(
            'communications',
            (new SendContributionCardJob(1))->queue
        );
    }

    public function test_invitation_delivery_jobs_use_the_communications_queue(): void
    {
        $this->assertSame(
            'communications',
            (new SendInvitationSmsJob(eventId: 1, inviteeId: 1))->queue
        );

        $this->assertSame(
            'communications',
            (new SendInvitationWhatsAppJob(inviteeId: 1))->queue
        );
    }
}
