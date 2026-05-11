<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Remove duplicate names inside the same event before enforcing uniqueness.
         * Keeps the first record and deletes later duplicates.
         */
        DB::statement("
            DELETE FROM invitees a
            USING invitees b
            WHERE a.id > b.id
            AND a.event_id = b.event_id
            AND LOWER(a.name) = LOWER(b.name)
        ");

        /*
         * PostgreSQL-safe check before adding unique constraint.
         */
        $constraintExists = DB::table('pg_constraint')
            ->where('conname', 'invitees_event_name_unique')
            ->exists();

        if (! $constraintExists) {
            DB::statement('
                ALTER TABLE invitees
                ADD CONSTRAINT invitees_event_name_unique
                UNIQUE (event_id, name)
            ');
        }
    }

    public function down(): void
    {
        $constraintExists = DB::table('pg_constraint')
            ->where('conname', 'invitees_event_name_unique')
            ->exists();

        if ($constraintExists) {
            DB::statement('
                ALTER TABLE invitees
                DROP CONSTRAINT invitees_event_name_unique
            ');
        }
    }
};