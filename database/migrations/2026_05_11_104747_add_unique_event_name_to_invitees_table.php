<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Remove duplicate invitee names within the same event.
        // Keep the earliest record and delete later duplicates.
        if ($driver === 'pgsql') {
            DB::statement('
                DELETE FROM invitees AS duplicate
                USING invitees AS original
                WHERE duplicate.id > original.id
                AND duplicate.event_id = original.event_id
                AND LOWER(duplicate.name) = LOWER(original.name)
            ');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                DELETE duplicate
                FROM invitees AS duplicate
                INNER JOIN invitees AS original
                    ON duplicate.event_id = original.event_id
                    AND LOWER(duplicate.name) = LOWER(original.name)
                    AND duplicate.id > original.id
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                DELETE FROM invitees
                WHERE id NOT IN (
                    SELECT MIN(id)
                    FROM invitees
                    GROUP BY event_id, LOWER(name)
                )
            ');
        } else {
            throw new RuntimeException(
                "Unsupported database driver: {$driver}"
            );
        }

        Schema::table('invitees', function (Blueprint $table): void {
            $table->unique(
                ['event_id', 'name'],
                'invitees_event_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $table->dropUnique('invitees_event_name_unique');
        });
    }
};