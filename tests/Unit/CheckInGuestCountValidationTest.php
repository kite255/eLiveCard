<?php

namespace Tests\Unit;

use App\Models\Invitee;
use App\Services\CheckInService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInGuestCountValidationTest extends TestCase
{
    public static function invalidCounts(): array
    {
        return [
            'zero guests' => [0],
            'negative guests' => [-1],
            'large negative count' => [-100],
        ];
    }

    #[DataProvider('invalidCounts')]
    public function test_non_positive_guest_count_is_rejected_without_recording_check_in(int $count): void
    {
        // No database setup is necessary: invalid input must fail before any query.
        $result = app(CheckInService::class)->checkIn(new Invitee(), $count);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid Guest Count', $result['title']);
        $this->assertSame('At least one guest must be selected for check-in.', $result['message']);
    }
}
