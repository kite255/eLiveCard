<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_template_placeholders', function (Blueprint $table) {
            $table->string('qr_color')->nullable()->after('font_family');
            $table->string('qr_background_color')->nullable()->after('qr_color');
        });
    }

    public function down(): void
    {
        Schema::table('card_template_placeholders', function (Blueprint $table) {
            $table->dropColumn([
                'qr_color',
                'qr_background_color',
            ]);
        });
    }
};