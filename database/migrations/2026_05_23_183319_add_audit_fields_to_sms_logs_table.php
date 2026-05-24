<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sms_logs', 'send_source')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->string('send_source')
                    ->default('manual')
                    ->after('sms_type');
            });
        }

        if (! Schema::hasColumn('sms_logs', 'sent_by_user_id')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->foreignIdFor(User::class, 'sent_by_user_id')
                    ->nullable()
                    ->after('send_source')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('sms_logs', 'batch_id')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->string('batch_id')
                    ->nullable()
                    ->after('sent_by_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sms_logs', 'sent_by_user_id')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sent_by_user_id');
            });
        }

        if (Schema::hasColumn('sms_logs', 'send_source')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->dropColumn('send_source');
            });
        }

        if (Schema::hasColumn('sms_logs', 'batch_id')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->dropColumn('batch_id');
            });
        }
    }
};