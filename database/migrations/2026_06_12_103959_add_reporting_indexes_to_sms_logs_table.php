<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->index(
                ['event_id', 'status'],
                'sms_logs_event_status_index'
            );

            $table->index(
                ['event_id', 'created_at'],
                'sms_logs_event_created_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->dropIndex('sms_logs_event_status_index');
            $table->dropIndex('sms_logs_event_created_at_index');
        });
    }
};