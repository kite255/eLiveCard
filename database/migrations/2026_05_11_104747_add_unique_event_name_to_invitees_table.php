<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate invitee names inside the same event before adding unique constraint.
        // Keep the first record and delete later duplicates.
        DB::statement("
            DELETE FROM invitees a
            USING invitees b
            WHERE a.id > b.id
            AND a.event_id = b.event_id
            AND LOWER(a.name) = LOWER(b.name)
        ");

        Schema::table('invitees', function (Blueprint $table) {
            $table->unique(['event_id', 'name'], 'invitees_event_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            $table->dropUnique('invitees_event_name_unique');
        });
    }
};