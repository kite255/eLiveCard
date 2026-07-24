<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'rsvp_share_token_hash')) {
                $table->string('rsvp_share_token_hash', 64)
                    ->nullable()
                    ->unique();
            }

            if (! Schema::hasColumn('events', 'rsvp_share_token_encrypted')) {
                $table->text('rsvp_share_token_encrypted')
                    ->nullable();
            }

            if (! Schema::hasColumn('events', 'rsvp_share_enabled')) {
                $table->boolean('rsvp_share_enabled')
                    ->default(false);
            }

            if (! Schema::hasColumn('events', 'rsvp_share_expires_at')) {
                $table->timestampTz('rsvp_share_expires_at')
                    ->nullable();
            }

            if (! Schema::hasColumn('events', 'rsvp_share_show_phone')) {
                $table->boolean('rsvp_share_show_phone')
                    ->default(false);
            }

            if (! Schema::hasColumn('events', 'rsvp_share_last_generated_at')) {
                $table->timestampTz('rsvp_share_last_generated_at')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('events', 'rsvp_share_token_hash')
                    ? 'rsvp_share_token_hash'
                    : null,
                Schema::hasColumn('events', 'rsvp_share_token_encrypted')
                    ? 'rsvp_share_token_encrypted'
                    : null,
                Schema::hasColumn('events', 'rsvp_share_enabled')
                    ? 'rsvp_share_enabled'
                    : null,
                Schema::hasColumn('events', 'rsvp_share_expires_at')
                    ? 'rsvp_share_expires_at'
                    : null,
                Schema::hasColumn('events', 'rsvp_share_show_phone')
                    ? 'rsvp_share_show_phone'
                    : null,
                Schema::hasColumn('events', 'rsvp_share_last_generated_at')
                    ? 'rsvp_share_last_generated_at'
                    : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
