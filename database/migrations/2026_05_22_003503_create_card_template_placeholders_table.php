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
        if (! Schema::hasTable('card_template_placeholders')) {
            Schema::create('card_template_placeholders', function (Blueprint $table) {
                $table->id();

                $table->foreignId('card_template_id')
                    ->constrained('card_templates')
                    ->cascadeOnDelete();

                $table->string('placeholder_key');
                $table->string('label')->nullable();

                $table->decimal('x_percent', 8, 4)->default(0);
                $table->decimal('y_percent', 8, 4)->default(0);
                $table->decimal('width_percent', 8, 4)->default(20);
                $table->decimal('height_percent', 8, 4)->default(8);

                $table->unsignedInteger('font_size')->default(32);
                $table->string('font_color')->default('#000000');
                $table->string('font_weight')->default('normal');
                $table->string('text_align')->default('left');

                $table->unsignedInteger('qr_size')->nullable();
                $table->string('qr_color')->nullable();
                $table->string('qr_background_color')->nullable();

                $table->boolean('is_visible')->default(true);
                $table->unsignedInteger('sort_order')->default(0);

                $table->timestamps();

                $table->unique(['card_template_id', 'placeholder_key']);
            });

            return;
        }

        Schema::table('card_template_placeholders', function (Blueprint $table) {
            if (! Schema::hasColumn('card_template_placeholders', 'card_template_id')) {
                $table->foreignId('card_template_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('card_templates')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('card_template_placeholders', 'placeholder_key')) {
                $table->string('placeholder_key')->nullable()->after('card_template_id');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'label')) {
                $table->string('label')->nullable()->after('placeholder_key');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'x_percent')) {
                $table->decimal('x_percent', 8, 4)->default(0)->after('label');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'y_percent')) {
                $table->decimal('y_percent', 8, 4)->default(0)->after('x_percent');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'width_percent')) {
                $table->decimal('width_percent', 8, 4)->default(20)->after('y_percent');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'height_percent')) {
                $table->decimal('height_percent', 8, 4)->default(8)->after('width_percent');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'font_size')) {
                $table->unsignedInteger('font_size')->default(32)->after('height_percent');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'font_color')) {
                $table->string('font_color')->default('#000000')->after('font_size');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'font_weight')) {
                $table->string('font_weight')->default('normal')->after('font_color');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'text_align')) {
                $table->string('text_align')->default('left')->after('font_weight');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'qr_size')) {
                $table->unsignedInteger('qr_size')->nullable()->after('text_align');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'qr_color')) {
                $table->string('qr_color')->nullable()->after('qr_size');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'qr_background_color')) {
                $table->string('qr_background_color')->nullable()->after('qr_color');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->after('qr_background_color');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_visible');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do not drop the table because it existed before this migration.
        // This keeps existing designer data safe.
    }
};