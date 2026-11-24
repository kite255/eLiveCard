<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $table->timestamp('invitation_phone_changed_at')->nullable();
            $table->unsignedBigInteger('invitation_sms_reset_log_id')->nullable();
            $table->unsignedBigInteger('invitation_message_reset_log_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $table->dropColumn(['invitation_phone_changed_at', 'invitation_sms_reset_log_id', 'invitation_message_reset_log_id']);
        });
    }
};
