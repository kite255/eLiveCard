<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_campaigns', function (Blueprint $table): void {
            $table->boolean('include_member_name')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('contribution_campaigns', function (Blueprint $table): void {
            $table->dropColumn('include_member_name');
        });
    }
};
