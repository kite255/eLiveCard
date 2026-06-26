<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_logs', 'provider_status')) {
                $table->string('provider_status')->nullable()->after('provider_message_id');
            }

            if (! Schema::hasColumn('sms_logs', 'provider_request')) {
                $table->json('provider_request')->nullable()->after('provider_status');
            }

            if (! Schema::hasColumn('sms_logs', 'delivery_report_checked_at')) {
                $table->timestamp('delivery_report_checked_at')->nullable()->after('failed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (Schema::hasColumn('sms_logs', 'delivery_report_checked_at')) {
                $table->dropColumn('delivery_report_checked_at');
            }

            if (Schema::hasColumn('sms_logs', 'provider_request')) {
                $table->dropColumn('provider_request');
            }

            if (Schema::hasColumn('sms_logs', 'provider_status')) {
                $table->dropColumn('provider_status');
            }
        });
    }
};