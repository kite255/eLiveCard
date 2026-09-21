<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('template_image');
            $table->decimal('name_x_percent', 7, 4)->default(10);
            $table->decimal('name_y_percent', 7, 4)->default(70);
            $table->decimal('name_width_percent', 7, 4)->default(80);
            $table->unsignedInteger('name_font_size')->default(42);
            $table->string('name_font_color', 7)->default('#111827');
            $table->string('name_font_family')->default('Montserrat');
            $table->string('name_font_weight')->default('bold');
            $table->string('name_text_align')->default('center');
            $table->string('whatsapp_template_name')->default('contribution_card_sw');
            $table->string('whatsapp_language_code', 10)->default('sw');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('contribution_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_campaign_id')
                ->constrained('contribution_campaigns')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('generated_card_path')->nullable();
            $table->string('generation_status')->default('pending');
            $table->string('send_status')->default('pending');
            $table->string('whatsapp_status')->default('not_sent');
            $table->string('provider_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['contribution_campaign_id', 'phone'],
                'contribution_campaign_phone_unique'
            );
            $table->index(['contribution_campaign_id', 'generation_status']);
            $table->index(['contribution_campaign_id', 'send_status']);
            $table->index(['event_id', 'whatsapp_status']);
        });

        Schema::table('message_logs', function (Blueprint $table): void {
            $table->foreignId('contribution_recipient_id')
                ->nullable()
                ->after('invitee_id')
                ->constrained('contribution_recipients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('message_logs')) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('contribution_recipient_id');
            });
        }

        Schema::dropIfExists('contribution_recipients');
        Schema::dropIfExists('contribution_campaigns');
    }
};
