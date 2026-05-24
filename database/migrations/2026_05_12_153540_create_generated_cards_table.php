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
        Schema::create('generated_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('invitee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('card_template_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('file_path')->nullable();

            $table->string('status')->default('pending');
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->unique(['invitee_id', 'card_template_id']);
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_cards');
    }
};