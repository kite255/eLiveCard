<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'invitees';

    private const RSVP_INDEX = 'invitees_rsvp_token_unique';

    public function up(): void
    {
        $addRsvpStatus = ! Schema::hasColumn(
            self::TABLE,
            'rsvp_status'
        );

        $addConfirmedGuests = ! Schema::hasColumn(
            self::TABLE,
            'confirmed_guests'
        );

        $addConfirmedAt = ! Schema::hasColumn(
            self::TABLE,
            'rsvp_confirmed_at'
        );

        $addRsvpToken = ! Schema::hasColumn(
            self::TABLE,
            'rsvp_token'
        );

        Schema::table(self::TABLE, function (Blueprint $table) use (
            $addRsvpStatus,
            $addConfirmedGuests,
            $addConfirmedAt,
            $addRsvpToken
        ): void {
            if ($addRsvpStatus) {
                $table->string('rsvp_status')
                    ->default('pending')
                    ->after('card_status');
            }

            if ($addConfirmedGuests) {
                $table->unsignedInteger('confirmed_guests')
                    ->nullable()
                    ->after('rsvp_status');
            }

            if ($addConfirmedAt) {
                $table->timestamp('rsvp_confirmed_at')
                    ->nullable()
                    ->after('confirmed_guests');
            }

            if ($addRsvpToken) {
                $table->string('rsvp_token')
                    ->nullable()
                    ->after('rsvp_confirmed_at');
            }
        });

        if (
            Schema::hasColumn(self::TABLE, 'rsvp_token')
            && ! Schema::hasIndex(self::TABLE, self::RSVP_INDEX)
        ) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    'rsvp_token',
                    self::RSVP_INDEX
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::RSVP_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::RSVP_INDEX);
            });
        }

        $columns = [
            'rsvp_status',
            'confirmed_guests',
            'rsvp_confirmed_at',
            'rsvp_token',
        ];

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool =>
                Schema::hasColumn(self::TABLE, $column)
        ));

        if ($existingColumns !== []) {
            Schema::table(
                self::TABLE,
                function (Blueprint $table) use ($existingColumns): void {
                    $table->dropColumn($existingColumns);
                }
            );
        }
    }
};