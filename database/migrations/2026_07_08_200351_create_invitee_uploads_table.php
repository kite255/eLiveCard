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
        if (! Schema::hasTable('invitee_uploads')) {
            Schema::create('invitee_uploads', function (Blueprint $table) {
                $table->id();

                $table->foreignId('event_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('invitee_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('type')->default('wish');
                // wish, photo

                $table->text('message')->nullable();
                // wish message or photo caption

                $table->string('file_path')->nullable();
                // uploaded photo path

                $table->string('status')->default('pending');
                // pending, approved, rejected

                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->text('admin_note')->nullable();

                $table->timestamps();

                $table->index(['event_id', 'status']);
                $table->index(['invitee_id', 'type']);
                $table->index(['type', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitee_uploads');
    }
};