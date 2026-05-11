<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $table->string('qr_token')->nullable()->unique()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};