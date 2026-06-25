<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckInService
{
    public function checkIn(
        Invitee $invitee,
        int $guestsCount,
        ?User $user = null,
        string $method = CheckIn::METHOD_QR
    ): array {
        $guestsCount = max(1, $guestsCount);

        try {
            return DB::transaction(function () use ($invitee, $guestsCount, $user, $method) {
                $lockedInvitee = Invitee::query()
                    ->whereKey($invitee->id)
                    ->with('cardType')
                    ->lockForUpdate()
                    ->firstOrFail();

                $allowedGuests = (int) (
                    $lockedInvitee->allowed_guests
                    ?: $lockedInvitee->cardType?->allowed_guests
                    ?: 1
                );

                $previousCheckedInCount = (int) ($lockedInvitee->checked_in_count ?? 0);
                $remainingBeforeCheckIn = max(0, $allowedGuests - $previousCheckedInCount);

                if (($lockedInvitee->card_status ?? 'active') !== 'active') {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: $remainingBeforeCheckIn,
                        status: CheckIn::STATUS_BLOCKED,
                        remarks: 'Card is not active.'
                    );

                    return [
                        'success' => false,
                        'title' => 'Blocked Card',
                        'message' => 'This invitation card is not active.',
                    ];
                }

                if ($remainingBeforeCheckIn <= 0) {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: 0,
                        status: CheckIn::STATUS_DUPLICATE,
                        remarks: 'Guest limit already reached.'
                    );

                    return [
                        'success' => false,
                        'title' => 'Guest Limit Reached',
                        'message' => 'This card has already used all allowed entries.',
                    ];
                }

                if ($guestsCount > $remainingBeforeCheckIn) {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: $remainingBeforeCheckIn,
                        status: CheckIn::STATUS_FAILED,
                        remarks: "Only {$remainingBeforeCheckIn} guest(s) remaining."
                    );

                    return [
                        'success' => false,
                        'title' => 'Too Many Guests',
                        'message' => "Only {$remainingBeforeCheckIn} guest(s) remaining for this card.",
                    ];
                }

                $newCheckedInCount = $previousCheckedInCount + $guestsCount;
                $remainingAfterCheckIn = max(0, $allowedGuests - $newCheckedInCount);

                $lockedInvitee->update([
                    'checked_in_count' => $newCheckedInCount,
                    'check_in_status' => $remainingAfterCheckIn <= 0
                        ? 'checked_in'
                        : 'partially_checked_in',
                    'checked_in_at' => now(),
                ]);

                $this->recordAttempt(
                    invitee: $lockedInvitee,
                    user: $user,
                    method: $method,
                    guestsCheckedIn: $guestsCount,
                    previousCheckedInCount: $previousCheckedInCount,
                    remainingGuests: $remainingAfterCheckIn,
                    status: CheckIn::STATUS_SUCCESS,
                    remarks: "{$guestsCount} guest(s) checked in successfully."
                );

                return [
                    'success' => true,
                    'title' => 'Check-in Successful',
                    'message' => "{$guestsCount} guest(s) checked in successfully.",
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'success' => false,
                'title' => 'Check-in Failed',
                'message' => 'Something went wrong while checking in this guest.',
            ];
        }
    }

    private function recordAttempt(
        Invitee $invitee,
        ?User $user,
        string $method,
        int $guestsCheckedIn,
        int $previousCheckedInCount,
        int $remainingGuests,
        string $status,
        ?string $remarks = null
    ): CheckIn {
        return CheckIn::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'checked_in_by' => $user?->id,
            'checkin_method' => $method,
            'guests_checked_in' => $guestsCheckedIn,
            'previous_checked_in_count' => $previousCheckedInCount,
            'remaining_guests' => $remainingGuests,
            'status' => $status,
            'remarks' => $remarks,
            'checked_in_at' => now(),
        ]);
    }
}