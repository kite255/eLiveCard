<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'card_template_placeholders';

    private const INDEX = 'card_template_placeholder_unique_key';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'key')) {
            return;
        }

        // The index must be removed before its key column is dropped.
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn('key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'key')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->string('key')->nullable();
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    ['card_template_id', 'key'],
                    self::INDEX
                );
            });
        }
    }
};