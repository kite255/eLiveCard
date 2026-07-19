<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('rsvp_share_token', 64)
                ->nullable()
                ->unique();

            $table->boolean('rsvp_share_enabled')
                ->default(false);

            $table->timestamp('rsvp_share_expires_at')
                ->nullable();

            $table->boolean('rsvp_share_show_phone')
                ->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['rsvp_share_token']);

            $table->dropColumn([
                'rsvp_share_token',
                'rsvp_share_enabled',
                'rsvp_share_expires_at',
                'rsvp_share_show_phone',
            ]);
        });
    }
};