<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\Invitee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class GateVerifyController extends Controller
{
    public function show(string $token)
    {
        $invitee = $this->findInviteeByToken($token);

        if (! $invitee) {
            return view('gate.invalid', [
                'message' => 'Invalid or unknown QR code.',
            ]);
        }

        $validationMessage = $this->validateInviteeForCheckIn($invitee);

        if ($validationMessage) {
            return view('gate.invalid', [
                'message' => $validationMessage,
            ]);
        }

        $allowedGuests = $this->allowedGuests($invitee);
        $confirmedGuests = $this->confirmedGuests($invitee);
        $gateLimit = $this->gateGuestLimit($invitee);

        $checkedInCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max(0, $gateLimit - $checkedInCount);

        return view('gate.verify', [
            'invitee' => $invitee,
            'token' => $token,

            // Original invitation/card limit
            'allowedGuests' => $allowedGuests,

            // RSVP confirmed guests
            'confirmedGuests' => $confirmedGuests,

            // Actual limit gate should use
            'gateLimit' => $gateLimit,

            'checkedInCount' => $checkedInCount,
            'remainingGuests' => $remainingGuests,
        ]);
    }

    public function checkIn(Request $request, string $token)
    {
        $request->validate([
            'guests_to_check_in' => ['required', 'integer', 'min:1'],
        ]);

        $guestsToCheckIn = (int) $request->guests_to_check_in;
        $tokenHash = hash('sha256', $token);

        try {
            DB::transaction(function () use ($tokenHash, $guestsToCheckIn): void {
                $invitee = Invitee::query()
                    ->with(['event', 'cardType'])
                    ->where('qr_token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if (! $invitee) {
                    throw new \RuntimeException('Invalid QR code.');
                }

                $validationMessage = $this->validateInviteeForCheckIn($invitee);

                if ($validationMessage) {
                    $this->recordFailedAttempt($invitee, $validationMessage);

                    throw new \RuntimeException($validationMessage);
                }

                $gateLimit = $this->gateGuestLimit($invitee);
                $previousCount = (int) ($invitee->checked_in_count ?? 0);
                $remainingBeforeCheckIn = max(0, $gateLimit - $previousCount);

                if ($remainingBeforeCheckIn <= 0) {
                    $this->recordDuplicateAttempt($invitee);

                    throw new \RuntimeException('Guest limit already reached.');
                }

                if ($guestsToCheckIn > $remainingBeforeCheckIn) {
                    $message = 'Only ' . $remainingBeforeCheckIn . ' guest(s) remaining.';

                    $this->recordFailedAttempt($invitee, $message);

                    throw new \RuntimeException($message);
                }

                $newCount = $previousCount + $guestsToCheckIn;
                $remainingAfterCheckIn = max(0, $gateLimit - $newCount);

                $invitee->checkIns()->create([
                    'event_id' => $invitee->event_id,
                    'checked_in_by' => Auth::id(),
                    'checkin_method' => CheckIn::METHOD_QR,
                    'guests_checked_in' => $guestsToCheckIn,
                    'previous_checked_in_count' => $previousCount,
                    'remaining_guests' => $remainingAfterCheckIn,
                    'status' => CheckIn::STATUS_SUCCESS,
                    'remarks' => $this->successRemarks($invitee),
                    'checked_in_at' => now(),
                ]);

                $invitee->update([
                    'checked_in_count' => $newCount,
                    'check_in_status' => $remainingAfterCheckIn <= 0
                        ? 'checked_in'
                        : 'partially_checked_in',
                    'checked_in_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gate.verify.show', $token)
            ->with('success', $guestsToCheckIn . ' guest(s) checked in successfully.');
    }

    private function findInviteeByToken(string $token): ?Invitee
    {
        $tokenHash = hash('sha256', $token);

        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where('qr_token_hash', $tokenHash)
            ->first();
    }

    private function validateInviteeForCheckIn(Invitee $invitee): ?string
    {
        $cardStatus = $invitee->card_status ?? 'active';

        if ($cardStatus === 'blocked') {
            return 'This invitation card is blocked.';
        }

        if ($cardStatus === 'cancelled') {
            return 'This invitation card is cancelled.';
        }

        $allowedCardStatuses = [
            'active',
            'generated',
            'sent',
        ];

        if (! in_array($cardStatus, $allowedCardStatuses, true)) {
            return 'This invitation card is not valid for check-in.';
        }

        if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
            return 'This invitee responded that they will not attend. Please contact the event manager before allowing check-in.';
        }

        if ($this->gateGuestLimit($invitee) <= 0) {
            return 'No guests are allowed for check-in on this invitation.';
        }

        return null;
    }

    private function allowedGuests(Invitee $invitee): int
    {
        if (isset($invitee->final_allowed_guests) && (int) $invitee->final_allowed_guests > 0) {
            return (int) $invitee->final_allowed_guests;
        }

        if ((int) ($invitee->allowed_guests ?? 0) > 0) {
            return (int) $invitee->allowed_guests;
        }

        if ((int) ($invitee->cardType?->allowed_guests ?? 0) > 0) {
            return (int) $invitee->cardType->allowed_guests;
        }

        if ((int) ($invitee->cardType?->allowed_people ?? 0) > 0) {
            return (int) $invitee->cardType->allowed_people;
        }

        return 1;
    }

    private function confirmedGuests(Invitee $invitee): int
    {
        return max(0, (int) ($invitee->confirmed_guests ?? 0));
    }

    private function gateGuestLimit(Invitee $invitee): int
    {
        $allowedGuests = $this->allowedGuests($invitee);
        $confirmedGuests = $this->confirmedGuests($invitee);

        if ($invitee->rsvp_status === 'attending' && $confirmedGuests > 0) {
            return min($confirmedGuests, $allowedGuests);
        }

        if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
            return 0;
        }

        return $allowedGuests;
    }

    private function successRemarks(Invitee $invitee): string
    {
        if ($invitee->rsvp_status === 'attending' && $this->confirmedGuests($invitee) > 0) {
            return 'Checked in by QR code scan using RSVP confirmed guest limit.';
        }

        return 'Checked in by QR code scan using allowed guest limit.';
    }

    private function recordFailedAttempt(Invitee $invitee, string $message): void
    {
        $gateLimit = $this->gateGuestLimit($invitee);
        $previousCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max(0, $gateLimit - $previousCount);

        $invitee->checkIns()->create([
            'event_id' => $invitee->event_id,
            'checked_in_by' => Auth::id(),
            'checkin_method' => CheckIn::METHOD_QR,
            'guests_checked_in' => 0,
            'previous_checked_in_count' => $previousCount,
            'remaining_guests' => $remainingGuests,
            'status' => CheckIn::STATUS_FAILED,
            'remarks' => $message,
            'checked_in_at' => now(),
        ]);
    }

    private function recordDuplicateAttempt(Invitee $invitee): void
    {
        $previousCount = (int) ($invitee->checked_in_count ?? 0);

        $invitee->checkIns()->create([
            'event_id' => $invitee->event_id,
            'checked_in_by' => Auth::id(),
            'checkin_method' => CheckIn::METHOD_QR,
            'guests_checked_in' => 0,
            'previous_checked_in_count' => $previousCount,
            'remaining_guests' => 0,
            'status' => CheckIn::STATUS_DUPLICATE,
            'remarks' => 'Guest limit already reached.',
            'checked_in_at' => now(),
        ]);
    }
}