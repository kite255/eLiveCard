<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('google_maps_link');
            }

            if (! Schema::hasColumn('events', 'welcome_message')) {
                $table->text('welcome_message')->nullable()->after('cover_image');
            }

            if (! Schema::hasColumn('events', 'love_story')) {
                $table->longText('love_story')->nullable()->after('welcome_message');
            }

            if (! Schema::hasColumn('events', 'program')) {
                $table->longText('program')->nullable()->after('love_story');
            }

            if (! Schema::hasColumn('events', 'organizer_phone')) {
                $table->string('organizer_phone')->nullable()->after('program');
            }

            if (! Schema::hasColumn('events', 'show_cover_image')) {
                $table->boolean('show_cover_image')->default(true)->after('organizer_phone');
            }

            if (! Schema::hasColumn('events', 'show_love_story')) {
                $table->boolean('show_love_story')->default(false)->after('show_cover_image');
            }

            if (! Schema::hasColumn('events', 'show_program')) {
                $table->boolean('show_program')->default(true)->after('show_love_story');
            }

            if (! Schema::hasColumn('events', 'show_countdown')) {
                $table->boolean('show_countdown')->default(true)->after('show_program');
            }

            if (! Schema::hasColumn('events', 'show_wishes')) {
                $table->boolean('show_wishes')->default(true)->after('show_countdown');
            }

            if (! Schema::hasColumn('events', 'show_organizer_contact')) {
                $table->boolean('show_organizer_contact')->default(true)->after('show_wishes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $columns = [
                'cover_image',
                'welcome_message',
                'love_story',
                'program',
                'organizer_phone',
                'show_cover_image',
                'show_love_story',
                'show_program',
                'show_countdown',
                'show_wishes',
                'show_organizer_contact',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};