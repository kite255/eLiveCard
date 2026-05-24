<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Event::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name');
            $table->string('sms_type');
            $table->text('message');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['event_id', 'sms_type']);
            $table->index(['sms_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};