<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $table->timestamp('invitation_phone_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invitees', function (Blueprint $table): void {
            $table->dropColumn('invitation_phone_changed_at');
        });
    }
};
