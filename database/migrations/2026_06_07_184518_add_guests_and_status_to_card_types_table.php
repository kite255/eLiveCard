<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_types', function (Blueprint $table) {
            if (! Schema::hasColumn('card_types', 'guests')) {
                $table->unsignedInteger('guests')->default(1)->after('name');
            }

            if (! Schema::hasColumn('card_types', 'status')) {
                $table->string('status')->default('active')->after('guests');
            }
        });
    }

    public function down(): void
    {
        Schema::table('card_types', function (Blueprint $table) {
            if (Schema::hasColumn('card_types', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('card_types', 'guests')) {
                $table->dropColumn('guests');
            }
        });
    }
};