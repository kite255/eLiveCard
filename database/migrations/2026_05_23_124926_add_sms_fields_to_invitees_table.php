<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'sms_status')) {
                $table->string('sms_status')->default('not_sent')->after('rsvp_confirmed_at');
            }

            if (! Schema::hasColumn('invitees', 'sms_sent_at')) {
                $table->timestamp('sms_sent_at')->nullable()->after('sms_status');
            }

            if (! Schema::hasColumn('invitees', 'sms_message_id')) {
                $table->string('sms_message_id')->nullable()->after('sms_sent_at');
            }

            if (! Schema::hasColumn('invitees', 'sms_error')) {
                $table->text('sms_error')->nullable()->after('sms_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $columns = [
                'sms_status',
                'sms_sent_at',
                'sms_message_id',
                'sms_error',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};