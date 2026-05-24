<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_logs', 'send_source')) {
                $table->string('send_source')
                    ->default('manual')
                    ->after('sms_type');
            }

            if (! Schema::hasColumn('sms_logs', 'sent_by_user_id')) {
                $table->foreignIdFor(User::class, 'sent_by_user_id')
                    ->nullable()
                    ->after('send_source')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('sms_logs', 'batch_id')) {
                $table->string('batch_id')
                    ->nullable()
                    ->after('sent_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (Schema::hasColumn('sms_logs', 'sent_by_user_id')) {
                $table->dropConstrainedForeignId('sent_by_user_id');
            }

            if (Schema::hasColumn('sms_logs', 'send_source')) {
                $table->dropColumn('send_source');
            }

            if (Schema::hasColumn('sms_logs', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
        });
    }
};