<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('card_template_placeholders')) {
            return;
        }

        if (Schema::hasColumn('card_template_placeholders', 'key')) {
            DB::statement('ALTER TABLE card_template_placeholders DROP COLUMN "key"');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('card_template_placeholders')) {
            return;
        }

        if (! Schema::hasColumn('card_template_placeholders', 'key')) {
            Schema::table('card_template_placeholders', function (Blueprint $table) {
                $table->string('key')->nullable();
            });
        }
    }
};