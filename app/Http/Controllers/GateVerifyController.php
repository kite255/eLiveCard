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
        $checkedInCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max(0, $allowedGuests - $checkedInCount);

        return view('gate.verify', [
            'invitee' => $invitee,
            'token' => $token,
            'allowedGuests' => $allowedGuests,
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

                $allowedGuests = $this->allowedGuests($invitee);
                $previousCount = (int) ($invitee->checked_in_count ?? 0);
                $remainingBeforeCheckIn = max(0, $allowedGuests - $previousCount);

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
                $remainingAfterCheckIn = max(0, $allowedGuests - $newCount);

                $invitee->checkIns()->create([
                    'event_id' => $invitee->event_id,
                    'checked_in_by' => Auth::id(),
                    'checkin_method' => CheckIn::METHOD_QR,
                    'guests_checked_in' => $guestsToCheckIn,
                    'previous_checked_in_count' => $previousCount,
                    'remaining_guests' => $remainingAfterCheckIn,
                    'status' => CheckIn::STATUS_SUCCESS,
                    'remarks' => 'Checked in by QR code scan.',
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

        return null;
    }

    private function allowedGuests(Invitee $invitee): int
    {
        return (int) (
            $invitee->allowed_guests
            ?: $invitee->cardType?->allowed_guests
            ?: 1
        );
    }

    private function recordFailedAttempt(Invitee $invitee, string $message): void
    {
        $allowedGuests = $this->allowedGuests($invitee);
        $previousCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max(0, $allowedGuests - $previousCount);

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