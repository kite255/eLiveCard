<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invitees', 'qr_token')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->string('qr_token')->nullable()->unique()->after('serial_number');
            });
        }

        DB::table('invitees')
            ->whereNull('qr_token')
            ->orderBy('id')
            ->chunkById(100, function ($invitees) {
                foreach ($invitees as $invitee) {
                    do {
                        $token = Str::random(64);
                    } while (DB::table('invitees')->where('qr_token', $token)->exists());

                    DB::table('invitees')
                        ->where('id', $invitee->id)
                        ->update([
                            'qr_token' => $token,
                            'qr_token_hash' => hash('sha256', $token),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invitees', 'qr_token')) {
            Schema::table('invitees', function (Blueprint $table) {
                $table->dropColumn('qr_token');
            });
        }
    }
};