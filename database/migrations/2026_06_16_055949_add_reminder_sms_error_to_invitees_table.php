<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'reminder_sms_error')) {
                $table->text('reminder_sms_error')->nullable()->after('reminder_sms_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (Schema::hasColumn('invitees', 'reminder_sms_error')) {
                $table->dropColumn('reminder_sms_error');
            }
        });
    }
};