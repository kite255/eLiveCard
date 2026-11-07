<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('message_templates', 'whatsapp_language_code')) {
            return;
        }

        // SMS templates have no Meta language code. Their explicit NULL
        // previously failed against the original NOT NULL column.
        Schema::table('message_templates', function (Blueprint $table): void {
            $table->string('whatsapp_language_code', 10)
                ->nullable()
                ->default('en')
                ->change();
        });
    }

    public function down(): void
    {
        // Keep nullable on rollback: existing SMS templates may contain NULL,
        // and restoring NOT NULL would cause a data-loss/rollback failure.
    }
};
