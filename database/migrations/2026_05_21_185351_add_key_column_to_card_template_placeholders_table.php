<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_template_placeholders', function (Blueprint $table) {
            if (! Schema::hasColumn('card_template_placeholders', 'key')) {
                $table->string('key')->nullable()->after('card_template_id');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'label')) {
                $table->string('label')->nullable()->after('key');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'type')) {
                $table->string('type')->default('text')->after('label');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'x')) {
                $table->integer('x')->default(0)->after('type');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'y')) {
                $table->integer('y')->default(0)->after('x');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'width')) {
                $table->integer('width')->default(120)->after('y');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'height')) {
                $table->integer('height')->default(40)->after('width');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'font_family')) {
                $table->string('font_family')->default('Poppins')->after('height');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'font_size')) {
                $table->integer('font_size')->default(16)->after('font_family');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'font_color')) {
                $table->string('font_color')->default('#3A2A1A')->after('font_size');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'alignment')) {
                $table->string('alignment')->default('center')->after('font_color');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('alignment');
            }

            if (! Schema::hasColumn('card_template_placeholders', 'show_border')) {
                $table->boolean('show_border')->default(true)->after('is_active');
            }
        });

        $rows = DB::table('card_template_placeholders')
            ->whereNull('key')
            ->orWhere('key', '')
            ->get();

        foreach ($rows as $row) {
            $label = $row->label ?? 'placeholder';

            DB::table('card_template_placeholders')
                ->where('id', $row->id)
                ->update([
                    'key' => Str::slug($label, '_') . '_' . $row->id,
                    'label' => $row->label ?? Str::headline($label),
                    'type' => $row->type ?? 'text',
                ]);
        }

        Schema::table('card_template_placeholders', function (Blueprint $table) {
            $table->string('key')->nullable(false)->change();
        });

        try {
            Schema::table('card_template_placeholders', function (Blueprint $table) {
                $table->unique(['card_template_id', 'key'], 'card_template_placeholder_unique_key');
            });
        } catch (Throwable $exception) {
            //
        }
    }

    public function down(): void
    {
        Schema::table('card_template_placeholders', function (Blueprint $table) {
            try {
                $table->dropUnique('card_template_placeholder_unique_key');
            } catch (Throwable $exception) {
                //
            }

            if (Schema::hasColumn('card_template_placeholders', 'key')) {
                $table->dropColumn('key');
            }
        });
    }
};