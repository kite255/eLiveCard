<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'card_sent_at')) {
                $table->timestamp('card_sent_at')->nullable()->after('rsvp_confirmed_at');
            }

            if (! Schema::hasColumn('invitees', 'message_status')) {
                $table->string('message_status')->default('not_sent')->after('card_sent_at');
            }

            if (! Schema::hasColumn('invitees', 'whatsapp_message_id')) {
                $table->string('whatsapp_message_id')->nullable()->after('message_status');
            }

            if (! Schema::hasColumn('invitees', 'last_message_error')) {
                $table->text('last_message_error')->nullable()->after('whatsapp_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $table->dropColumn([
                'card_sent_at',
                'message_status',
                'whatsapp_message_id',
                'last_message_error',
            ]);
        });
    }
};