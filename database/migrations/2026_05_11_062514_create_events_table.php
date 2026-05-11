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
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('event_type')->nullable();

            $table->date('event_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->text('google_maps_link')->nullable();

            $table->string('dress_code')->nullable();
            $table->text('program')->nullable();

            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();

            $table->string('status')->default('draft');

            $table->timestamps();

            $table->index('user_id');
            $table->index('event_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};