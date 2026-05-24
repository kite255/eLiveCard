<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_template_placeholders', function (Blueprint $table) {
            if (! Schema::hasColumn('card_template_placeholders', 'font_family')) {
                $table->string('font_family')->default('Montserrat')->after('font_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('card_template_placeholders', function (Blueprint $table) {
            if (Schema::hasColumn('card_template_placeholders', 'font_family')) {
                $table->dropColumn('font_family');
            }
        });
    }
};