<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->time('rsvp_pending_reminder_time')
                ->nullable()
                ->default('09:00:00')
                ->after('auto_rsvp_pending_reminder_enabled');

            $table->time('one_day_reminder_time')
                ->nullable()
                ->default('10:00:00')
                ->after('auto_one_day_reminder_enabled');

            $table->time('event_day_reminder_time')
                ->nullable()
                ->default('06:00:00')
                ->after('auto_event_day_reminder_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'rsvp_pending_reminder_time',
                'one_day_reminder_time',
                'event_day_reminder_time',
            ]);
        });
    }
};
