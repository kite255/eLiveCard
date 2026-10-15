<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_logs', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('message_logs')->whereNull('event_id')->exists()) {
            throw new RuntimeException('Cannot restore required event_id while standalone contribution logs exist.');
        }

        Schema::table('message_logs', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable(false)->change();
        });
    }
};
