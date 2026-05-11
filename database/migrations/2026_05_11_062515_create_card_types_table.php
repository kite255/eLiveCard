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
        Schema::create('card_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            // Number of people allowed within this card type.
            // Example: Single = 1, Double = 2, Family = 5.
            $table->unsignedInteger('allowed_people')->default(1);

            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['event_id', 'name']);
            $table->index('event_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_types');
    }
};