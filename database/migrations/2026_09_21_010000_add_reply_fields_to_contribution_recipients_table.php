<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_recipients', function (Blueprint $table): void {
            $table->text('last_reply_message')->nullable()->after('last_error');
            $table->timestamp('last_reply_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_recipients', function (Blueprint $table): void {
            $table->dropColumn([
                'last_reply_message',
                'last_reply_at',
            ]);
        });
    }
};
