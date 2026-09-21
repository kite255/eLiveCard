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
        $seen = [];
        $duplicateIds = [];

        DB::table('invitees')
            ->select(['id', 'event_id', 'name'])
            ->orderBy('id')
            ->each(function (object $invitee) use (&$seen, &$duplicateIds): void {
                $key = $invitee->event_id.'|'.mb_strtolower(trim((string) $invitee->name));

                if (isset($seen[$key])) {
                    $duplicateIds[] = $invitee->id;

                    return;
                }

                $seen[$key] = true;
            });

        if ($duplicateIds !== []) {
            DB::table('invitees')->whereIn('id', $duplicateIds)->delete();
        }

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
