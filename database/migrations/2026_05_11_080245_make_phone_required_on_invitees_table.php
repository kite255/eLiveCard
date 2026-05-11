<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove test invitees without phone numbers before making phone required.
        DB::table('invitees')->whereNull('phone')->delete();

        Schema::table('invitees', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }
};