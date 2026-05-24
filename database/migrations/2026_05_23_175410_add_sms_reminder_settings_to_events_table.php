<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'auto_sms_reminders_enabled')) {
                $table->boolean('auto_sms_reminders_enabled')
                    ->default(false)
                    ->after('status');
            }

            if (! Schema::hasColumn('events', 'auto_rsvp_pending_reminder_enabled')) {
                $table->boolean('auto_rsvp_pending_reminder_enabled')
                    ->default(true)
                    ->after('auto_sms_reminders_enabled');
            }

            if (! Schema::hasColumn('events', 'auto_one_day_reminder_enabled')) {
                $table->boolean('auto_one_day_reminder_enabled')
                    ->default(true)
                    ->after('auto_rsvp_pending_reminder_enabled');
            }

            if (! Schema::hasColumn('events', 'auto_event_day_reminder_enabled')) {
                $table->boolean('auto_event_day_reminder_enabled')
                    ->default(true)
                    ->after('auto_one_day_reminder_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $columns = [
                'auto_sms_reminders_enabled',
                'auto_rsvp_pending_reminder_enabled',
                'auto_one_day_reminder_enabled',
                'auto_event_day_reminder_enabled',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};