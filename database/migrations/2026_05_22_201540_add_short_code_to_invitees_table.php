<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'short_code')) {
                $table->string('short_code', 20)
                    ->nullable()
                    ->unique()
                    ->after('serial_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (Schema::hasColumn('invitees', 'short_code')) {
                $table->dropColumn('short_code');
            }
        });
    }
};