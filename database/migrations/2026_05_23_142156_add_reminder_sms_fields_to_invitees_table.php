<?php

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Event::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(Invitee::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('phone');
            $table->string('sms_type');

            $table->text('message');

            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->json('provider_response')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'sms_type']);
            $table->index(['invitee_id', 'sms_type']);
            $table->index(['status']);
            $table->index(['provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};