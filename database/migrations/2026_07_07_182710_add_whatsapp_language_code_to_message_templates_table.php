<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('message_templates', 'whatsapp_language_code')) {
                $table->string('whatsapp_language_code', 10)
                    ->default('en')
                    ->after('whatsapp_template_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('message_templates', 'whatsapp_language_code')) {
                $table->dropColumn('whatsapp_language_code');
            }
        });
    }
};