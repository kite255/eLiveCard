<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events', 'rsvp_share_token')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('rsvp_share_token');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('events', 'rsvp_share_token')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->string('rsvp_share_token', 64)
                    ->nullable()
                    ->unique();
            });
        }
    }
};