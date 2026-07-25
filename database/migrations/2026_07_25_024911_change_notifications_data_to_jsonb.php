<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert the notification payload from text to PostgreSQL JSONB.
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE notifications
             ALTER COLUMN data TYPE jsonb
             USING data::jsonb'
        );
    }

    /**
     * Convert the column back to text when rolling back.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE notifications
             ALTER COLUMN data TYPE text
             USING data::text'
        );
    }
};