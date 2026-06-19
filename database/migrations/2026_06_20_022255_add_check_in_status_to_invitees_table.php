<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'check_in_status')) {
                $table->string('check_in_status')
                    ->default('not_checked_in')
                    ->after('checked_in_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (Schema::hasColumn('invitees', 'check_in_status')) {
                $table->dropColumn('check_in_status');
            }
        });
    }
};