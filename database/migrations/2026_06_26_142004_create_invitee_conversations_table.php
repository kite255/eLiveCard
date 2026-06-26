<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invitee_conversations')) {
            Schema::create('invitee_conversations', function (Blueprint $table) {
                $table->id();

                $table->foreignId('event_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('invitee_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('sent_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('channel')->default('whatsapp');
                $table->string('direction')->default('incoming');

                $table->string('from_phone')->nullable();
                $table->string('to_phone')->nullable();

                $table->text('message');

                $table->string('status')->default('received');

                $table->string('provider_message_id')->nullable()->index();
                $table->json('provider_response')->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('received_at')->nullable();

                $table->timestamps();

                $table->index(['event_id', 'invitee_id']);
                $table->index(['channel', 'direction']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invitee_conversations');
    }
};