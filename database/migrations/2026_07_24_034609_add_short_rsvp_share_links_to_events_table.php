<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingColumns = [
            'rsvp_share_slug' => ! Schema::hasColumn(
                'events',
                'rsvp_share_slug'
            ),
            'rsvp_share_short_code_hash' => ! Schema::hasColumn(
                'events',
                'rsvp_share_short_code_hash'
            ),
            'rsvp_share_short_code_encrypted' => ! Schema::hasColumn(
                'events',
                'rsvp_share_short_code_encrypted'
            ),
        ];

        if (! in_array(true, $missingColumns, true)) {
            return;
        }

        Schema::table('events', function (Blueprint $table) use ($missingColumns): void {
            if ($missingColumns['rsvp_share_slug']) {
                $table->string('rsvp_share_slug', 100)
                    ->nullable();
            }

            if ($missingColumns['rsvp_share_short_code_hash']) {
                $table->string('rsvp_share_short_code_hash', 64)
                    ->nullable()
                    ->unique();
            }

            if ($missingColumns['rsvp_share_short_code_encrypted']) {
                $table->text('rsvp_share_short_code_encrypted')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $columns = [];

            foreach ([
                'rsvp_share_slug',
                'rsvp_share_short_code_hash',
                'rsvp_share_short_code_encrypted',
            ] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
