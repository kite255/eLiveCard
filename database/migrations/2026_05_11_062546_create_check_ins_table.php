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
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('invitee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('checked_in_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('checkin_method')->default('manual');

            $table->unsignedInteger('guests_checked_in')->default(1);
            $table->unsignedInteger('previous_checked_in_count')->default(0);
            $table->unsignedInteger('remaining_guests')->default(0);

            $table->string('status')->default('success');
            $table->text('remarks')->nullable();

            $table->timestamp('checked_in_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'invitee_id']);
            $table->index(['event_id', 'status']);
            $table->index('checked_in_by');
            $table->index('checked_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};