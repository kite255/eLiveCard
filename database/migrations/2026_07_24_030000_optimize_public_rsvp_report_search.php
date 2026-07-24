<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | PostgreSQL search support
        |--------------------------------------------------------------------------
        |
        | pg_trgm allows the public RSVP report to efficiently search inside
        | names, categories, and phone numbers with %term% matching.
        |
        */
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(
            'CREATE INDEX IF NOT EXISTS invitees_event_rsvp_status_index
             ON invitees (event_id, rsvp_status)'
        );

        DB::statement(
            "CREATE INDEX IF NOT EXISTS invitees_name_trgm_index
             ON invitees
             USING GIN (LOWER(COALESCE(name, '')) gin_trgm_ops)"
        );

        DB::statement(
            "CREATE INDEX IF NOT EXISTS invitees_category_trgm_index
             ON invitees
             USING GIN (LOWER(COALESCE(category, '')) gin_trgm_ops)"
        );

        DB::statement(
            "CREATE INDEX IF NOT EXISTS invitees_phone_trgm_index
             ON invitees
             USING GIN (LOWER(COALESCE(phone, '')) gin_trgm_ops)"
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS invitees_event_rsvp_status_index'
        );

        DB::statement(
            'DROP INDEX IF EXISTS invitees_name_trgm_index'
        );

        DB::statement(
            'DROP INDEX IF EXISTS invitees_category_trgm_index'
        );

        DB::statement(
            'DROP INDEX IF EXISTS invitees_phone_trgm_index'
        );

        /*
         * Do not remove pg_trgm because other application indexes may use it.
         */
    }
};
