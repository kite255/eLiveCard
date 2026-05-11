<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invitees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('card_type_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('category')->nullable();
            $table->string('table_number')->nullable();

            // Optional override.
            // If empty, the system will use card_types.allowed_people.
            $table->unsignedInteger('allowed_guests')->nullable();

            $table->string('serial_number')->unique();

            // Store hashed QR token for security.
            $table->string('qr_token_hash')->unique()->nullable();
            $table->string('qr_code_path')->nullable();

            $table->string('card_status')->default('pending');
            $table->string('rsvp_status')->default('pending');

            $table->timestamp('rsvp_confirmed_at')->nullable();

            $table->unsignedInteger('checked_in_count')->default(0);
            $table->timestamp('checked_in_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'phone']);
            $table->index(['event_id', 'serial_number']);
            $table->index(['event_id', 'rsvp_status']);
            $table->index(['event_id', 'card_status']);
            $table->index(['event_id', 'card_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitees');
    }
};