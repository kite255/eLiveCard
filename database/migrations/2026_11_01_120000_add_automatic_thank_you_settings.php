<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('thank_you_mode', 12)->default('manual');
            $table->string('auto_thank_you_scope', 20)->default('checked_in');
        });

        Schema::create('automatic_thank_you_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16)->default('sms');
            $table->string('status', 20)->default('attempted');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'invitee_id', 'channel'], 'auto_thank_you_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_thank_you_sends');
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['thank_you_mode', 'auto_thank_you_scope']);
        });
    }
};
