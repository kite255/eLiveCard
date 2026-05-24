<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('generated_cards', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('generated_cards', function (Blueprint $table) {
            if (Schema::hasColumn('generated_cards', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
        });
    }
};