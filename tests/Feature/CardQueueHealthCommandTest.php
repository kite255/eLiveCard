<?php

namespace Tests\Feature;

use App\Models\ContributionCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardQueueHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_missing_contribution_template_without_modifying_records(): void
    {
        Storage::fake('public');

        $campaign = ContributionCampaign::query()->create([
            'created_by' => User::factory()->create()->id,
            'name' => 'Test contributions',
            'event_id' => null,
            'template_image' => 'missing/contribution.jpg',
            'status' => ContributionCampaign::STATUS_DRAFT,
        ]);

        $failedJobsBefore = DB::table('failed_jobs')->count();
        $pendingJobsBefore = DB::table('jobs')->count();

        $this->assertSame(0, Artisan::call('elive:card-queue-health', ['--limit' => 5]));

        $output = Artisan::output();
        $this->assertStringContainsString('missing/contribution.jpg', $output);
        $this->assertStringContainsString('missing template files: 1', $output);
        $this->assertSame($failedJobsBefore, DB::table('failed_jobs')->count());
        $this->assertSame($pendingJobsBefore, DB::table('jobs')->count());
        $this->assertDatabaseHas('contribution_campaigns', [
            'id' => $campaign->id,
            'template_image' => 'missing/contribution.jpg',
        ]);
    }
}
