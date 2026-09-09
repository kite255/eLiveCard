<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('card_templates', 'source_width')) {
                $table->unsignedInteger('source_width')
                    ->nullable()
                    ->after('height');
            }

            if (! Schema::hasColumn('card_templates', 'source_height')) {
                $table->unsignedInteger('source_height')
                    ->nullable()
                    ->after('source_width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('card_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('card_templates', 'source_height')) {
                $table->dropColumn('source_height');
            }

            if (Schema::hasColumn('card_templates', 'source_width')) {
                $table->dropColumn('source_width');
            }
        });
    }
};
