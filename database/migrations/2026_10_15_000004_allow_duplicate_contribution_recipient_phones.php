<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_recipients', function (Blueprint $table): void {
            $table->dropUnique('contribution_campaign_phone_unique');
            $table->index(['contribution_campaign_id', 'phone'], 'contribution_campaign_phone_index');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_recipients', function (Blueprint $table): void {
            $table->dropIndex('contribution_campaign_phone_index');
            $table->unique(['contribution_campaign_id', 'phone'], 'contribution_campaign_phone_unique');
        });
    }
};
