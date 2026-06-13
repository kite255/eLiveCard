<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Event::class)
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('channel')->default('sms');
            $table->string('type')->default('invitation');
            $table->string('name');
            $table->text('content')->nullable();

            $table->string('whatsapp_template_name')->nullable();
            $table->json('whatsapp_buttons')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();

            $table->index(['event_id', 'channel', 'type']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};