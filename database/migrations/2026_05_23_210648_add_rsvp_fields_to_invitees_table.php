<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'rsvp_status')) {
                $table->string('rsvp_status')->default('pending')->after('card_status');
            }

            if (! Schema::hasColumn('invitees', 'confirmed_guests')) {
                $table->unsignedInteger('confirmed_guests')->nullable()->after('rsvp_status');
            }

            if (! Schema::hasColumn('invitees', 'rsvp_confirmed_at')) {
                $table->timestamp('rsvp_confirmed_at')->nullable()->after('confirmed_guests');
            }

            if (! Schema::hasColumn('invitees', 'rsvp_token')) {
                $table->string('rsvp_token')->nullable()->after('rsvp_confirmed_at');
            }
        });

        // PostgreSQL-safe unique index check
        $indexExists = collect(DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'invitees'
            AND indexname = 'invitees_rsvp_token_unique'
        "))->isNotEmpty();

        if (! $indexExists && Schema::hasColumn('invitees', 'rsvp_token')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->unique('rsvp_token');
            });
        }
    }

    public function down(): void
    {
        $indexExists = collect(DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'invitees'
            AND indexname = 'invitees_rsvp_token_unique'
        "))->isNotEmpty();

        if ($indexExists) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->dropUnique('invitees_rsvp_token_unique');
            });
        }

        Schema::table('invitees', function (Blueprint $table) {
            $columns = [
                'rsvp_status',
                'confirmed_guests',
                'rsvp_confirmed_at',
                'rsvp_token',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};