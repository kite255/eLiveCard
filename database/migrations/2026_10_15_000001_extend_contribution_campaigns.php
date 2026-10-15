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

        // Older event-linked campaigns are assigned to their event owner.
        \Illuminate\Support\Facades\DB::table('contribution_campaigns')
            ->join('events', 'contribution_campaigns.event_id', '=', 'events.id')
            ->update(['contribution_campaigns.created_by' => \Illuminate\Support\Facades\DB::raw('events.user_id')]);

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
