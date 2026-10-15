<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_campaigns', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->change();
            $table->foreignId('created_by')->nullable()->nullable()->constrained('users')->nullOnDelete();
            $table->string('campaign_type', 32)->default('wedding');
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
        });

        // Backfill in PHP for MySQL/PostgreSQL portability.
        \Illuminate\Support\Facades\DB::table('contribution_campaigns')
            ->whereNotNull('event_id')
            ->orderBy('id')
            ->chunkById(200, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    $ownerId = \Illuminate\Support\Facades\DB::table('events')
                        ->where('id', $campaign->event_id)
                        ->value('user_id');

                    if ($ownerId) {
                        \Illuminate\Support\Facades\DB::table('contribution_campaigns')
                            ->where('id', $campaign->id)
                            ->update(['created_by' => $ownerId]);
                    }
                }
            });

        Schema::table('contribution_recipients', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Nullable event_id is intentionally retained: reversing it would fail for
        // standalone campaigns and their recipients.
        Schema::table('contribution_campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['campaign_type', 'description', 'deadline']);
        });
    }
};
