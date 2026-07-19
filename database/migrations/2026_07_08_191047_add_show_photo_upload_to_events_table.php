<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'show_photo_upload')) {
                $table->boolean('show_photo_upload')
                    ->default(true)
                    ->after('show_wishes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'show_photo_upload')) {
                $table->dropColumn('show_photo_upload');
            }
        });
    }
};