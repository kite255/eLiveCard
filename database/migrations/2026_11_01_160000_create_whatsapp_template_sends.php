<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_template_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'invitee_id', 'message_template_id'], 'whatsapp_template_sends_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_sends');
    }
};
