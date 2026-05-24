<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'qr_token')) {
                $table->string('qr_token')->nullable()->unique()->after('serial_number');
            }

            if (! Schema::hasColumn('invitees', 'qr_code')) {
                $table->string('qr_code')->nullable()->after('qr_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (Schema::hasColumn('invitees', 'qr_code')) {
                $table->dropColumn('qr_code');
            }

            if (Schema::hasColumn('invitees', 'qr_token')) {
                $table->dropColumn('qr_token');
            }
        });
    }
};