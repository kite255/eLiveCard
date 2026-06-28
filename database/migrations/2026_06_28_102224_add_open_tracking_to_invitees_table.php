<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            if (! Schema::hasColumn('invitees', 'first_opened_at')) {
                $table->timestamp('first_opened_at')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'last_opened_at')) {
                $table->timestamp('last_opened_at')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'open_count')) {
                $table->unsignedInteger('open_count')->default(0);
            }

            if (! Schema::hasColumn('invitees', 'last_open_ip')) {
                $table->string('last_open_ip')->nullable();
            }

            if (! Schema::hasColumn('invitees', 'last_open_user_agent')) {
                $table->text('last_open_user_agent')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table) {
            foreach ([
                'first_opened_at',
                'last_opened_at',
                'open_count',
                'last_open_ip',
                'last_open_user_agent',
            ] as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};