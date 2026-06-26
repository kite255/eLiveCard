<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'last_sms_sent_at')) {
                $table->timestamp('last_sms_sent_at')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'last_whatsapp_sent_at')) {
                $table->timestamp('last_whatsapp_sent_at')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'last_message_channel')) {
                $table->string('last_message_channel')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'last_message_status')) {
                $table->string('last_message_status')->default('not_sent');
            }

            if (! Schema::hasColumn('invitees', 'last_reply_message')) {
                $table->text('last_reply_message')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'last_reply_at')) {
                $table->timestamp('last_reply_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $columns = [
                'last_sms_sent_at',
                'last_whatsapp_sent_at',
                'last_message_channel',
                'last_message_status',
                'last_reply_message',
                'last_reply_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};