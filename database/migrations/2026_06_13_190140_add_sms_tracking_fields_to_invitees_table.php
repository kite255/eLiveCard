<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invitees', 'invitation_sms_status')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->string('invitation_sms_status')
                    ->default('pending');
            });
        }

        if (! Schema::hasColumn('invitees', 'invitation_sms_sent_at')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->timestamp('invitation_sms_sent_at')->nullable();
            });
        }

        if (! Schema::hasColumn('invitees', 'reminder_sms_status')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->string('reminder_sms_status')
                    ->default('pending');
            });
        }

        if (! Schema::hasColumn('invitees', 'reminder_sms_sent_at')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->timestamp('reminder_sms_sent_at')->nullable();
            });
        }

        if (! Schema::hasColumn('invitees', 'final_sms_status')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->string('final_sms_status')
                    ->default('pending');
            });
        }

        if (! Schema::hasColumn('invitees', 'final_sms_sent_at')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->timestamp('final_sms_sent_at')->nullable();
            });
        }

        if (! Schema::hasColumn('invitees', 'last_sms_error')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->text('last_sms_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'invitation_sms_status',
            'invitation_sms_sent_at',
            'reminder_sms_status',
            'reminder_sms_sent_at',
            'final_sms_status',
            'final_sms_sent_at',
            'last_sms_error',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('invitees', $column)) {
                Schema::table('invitees', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};