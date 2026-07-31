<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'show_love_story')) {
                $table->boolean('show_love_story')
                    ->default(false)
                    ->after('welcome_message');
            }

            if (! Schema::hasColumn('events', 'love_story_language')) {
                $table->string('love_story_language', 2)
                    ->default('en')
                    ->after('show_love_story');
            }

            if (! Schema::hasColumn('events', 'love_story_en')) {
                $table->text('love_story_en')
                    ->nullable()
                    ->after('love_story_language');
            }

            if (! Schema::hasColumn('events', 'love_story_sw')) {
                $table->text('love_story_sw')
                    ->nullable()
                    ->after('love_story_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $columns = collect([
                'love_story_sw',
                'love_story_en',
                'love_story_language',
                'show_love_story',
            ])->filter(
                fn (string $column): bool =>
                    Schema::hasColumn('events', $column)
            )->values()->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
