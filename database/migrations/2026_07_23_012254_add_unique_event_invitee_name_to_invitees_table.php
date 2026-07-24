<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX invitees_event_normalized_name_unique
            ON invitees (
                event_id,
                LOWER(TRIM(name))
            )
        ');
    }

    public function down(): void
    {
        DB::statement('
            DROP INDEX IF EXISTS invitees_event_normalized_name_unique
        ');
    }
};