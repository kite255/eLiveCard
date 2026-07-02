<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            if (! Schema::hasColumn('invitees', 'whatsapp_status')) {
                $table->string('whatsapp_status')->default('not_sent')->after('sms_status');
            }

            if (! Schema::hasColumn('invitees', 'whatsapp_message_id')) {
                $table->string('whatsapp_message_id')->nullable()->after('whatsapp_status');
            }

            if (! Schema::hasColumn('invitees', 'whatsapp_sent_at')) {
                $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_message_id');
            }

            if (! Schema::hasColumn('invitees', 'whatsapp_delivered_at')) {
                $table->timestamp('whatsapp_delivered_at')->nullable()->after('whatsapp_sent_at');
            }

            if (! Schema::hasColumn('invitees', 'whatsapp_read_at')) {
                $table->timestamp('whatsapp_read_at')->nullable()->after('whatsapp_delivered_at');
            }

            if (! Schema::hasColumn('invitees', 'whatsapp_failed_at')) {
                $table->timestamp('whatsapp_failed_at')->nullable()->after('whatsapp_read_at');
            }

            if (! Schema::hasColumn('invitees', 'last_message_channel')) {
                $table->string('last_message_channel')->nullable()->after('whatsapp_failed_at');
            }

            if (! Schema::hasColumn('invitees', 'last_message_body')) {
                $table->text('last_message_body')->nullable()->after('last_message_channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $columns = [
                'whatsapp_status',
                'whatsapp_message_id',
                'whatsapp_sent_at',
                'whatsapp_delivered_at',
                'whatsapp_read_at',
                'whatsapp_failed_at',
                'last_message_channel',
                'last_message_body',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
