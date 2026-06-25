<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            if (! Schema::hasColumn('check_ins', 'device_name')) {
                $table->string('device_name')->nullable()->after('remarks');
            }

            if (! Schema::hasColumn('check_ins', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('device_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            if (Schema::hasColumn('check_ins', 'ip_address')) {
                $table->dropColumn('ip_address');
            }

            if (Schema::hasColumn('check_ins', 'device_name')) {
                $table->dropColumn('device_name');
            }
        });
    }
};