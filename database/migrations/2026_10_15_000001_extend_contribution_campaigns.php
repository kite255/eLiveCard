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
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
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
        if (Schema::table('contribution_recipients', fn (Blueprint $table) => $table->foreignId('event_id')->nullable()->change()) === null) {
            // Standalone rows must be migrated to an event before restoring a non-null foreign key.
        }
        Schema::table('contribution_campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['campaign_type', 'description', 'deadline']);
        });
    }
};
