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
        Schema::create('card_template_placeholders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('card_template_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('placeholder_key');

            $table->unsignedInteger('x_position')->default(0);
            $table->unsignedInteger('y_position')->default(0);

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->unsignedInteger('font_size')->default(24);
            $table->string('font_color')->default('#000000');
            $table->string('font_family')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['card_template_id', 'placeholder_key']);
            $table->index(['card_template_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_template_placeholders');
    }
};