<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('card_template_placeholders')) {
            return;
        }

        if (Schema::hasColumn('card_template_placeholders', 'key')) {
            DB::statement('ALTER TABLE card_template_placeholders DROP COLUMN "key"');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('card_template_placeholders')) {
            return;
        }

        if (! Schema::hasColumn('card_template_placeholders', 'key')) {
            DB::statement('ALTER TABLE card_template_placeholders ADD COLUMN "key" VARCHAR(255)');
        }
    }
};