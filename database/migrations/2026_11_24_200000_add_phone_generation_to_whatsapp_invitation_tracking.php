<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $table->unsignedInteger('invitation_phone_generation')->default(0);
        });

        Schema::table('whatsapp_template_sends', function (Blueprint $table): void {
            $table->unsignedInteger('phone_generation')->default(0);
            $table->dropUnique('whatsapp_template_sends_unique');
            $table->unique(['event_id', 'invitee_id', 'message_template_id', 'phone_generation'], 'whatsapp_template_sends_unique');
        });
    }

    public function down(): void
    {
        // A newer generation may have additional rows for the same invitee/template.
        // Removing them during rollback would destroy historical delivery records.
        throw new \RuntimeException('Do not roll back WhatsApp phone generations without archiving historical claims.');
    }
};
