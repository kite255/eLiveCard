<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingColumns = [
            'rsvp_share_token_hash' => ! Schema::hasColumn(
                'events',
                'rsvp_share_token_hash'
            ),

            'rsvp_share_token_encrypted' => ! Schema::hasColumn(
                'events',
                'rsvp_share_token_encrypted'
            ),

            'rsvp_share_enabled' => ! Schema::hasColumn(
                'events',
                'rsvp_share_enabled'
            ),

            'rsvp_share_expires_at' => ! Schema::hasColumn(
                'events',
                'rsvp_share_expires_at'
            ),

            'rsvp_share_show_phone' => ! Schema::hasColumn(
                'events',
                'rsvp_share_show_phone'
            ),

            'rsvp_share_last_generated_at' => ! Schema::hasColumn(
                'events',
                'rsvp_share_last_generated_at'
            ),
        ];

        if (! in_array(true, $missingColumns, true)) {
            return;
        }

        Schema::table('events', function (Blueprint $table) use ($missingColumns): void {
            if ($missingColumns['rsvp_share_token_hash']) {
                $table->string('rsvp_share_token_hash', 64)
                    ->nullable()
                    ->unique();
            }

            if ($missingColumns['rsvp_share_token_encrypted']) {
                $table->text('rsvp_share_token_encrypted')
                    ->nullable();
            }

            if ($missingColumns['rsvp_share_enabled']) {
                $table->boolean('rsvp_share_enabled')
                    ->default(false);
            }

            if ($missingColumns['rsvp_share_expires_at']) {
                $table->timestampTz('rsvp_share_expires_at')
                    ->nullable();
            }

            if ($missingColumns['rsvp_share_show_phone']) {
                $table->boolean('rsvp_share_show_phone')
                    ->default(false);
            }

            if ($missingColumns['rsvp_share_last_generated_at']) {
                $table->timestampTz('rsvp_share_last_generated_at')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Production Repair Migration
        |--------------------------------------------------------------------------
        |
        | Intentionally left empty.
        |
        | This migration repairs missing RSVP share columns on existing
        | installations. Rolling it back must not remove production data or
        | invalidate existing client report links.
        |
        */
    }
};
