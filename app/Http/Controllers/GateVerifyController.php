<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GateVerifyController extends Controller
{
    public function show(string $token)
    {
        $tokenHash = hash('sha256', $token);

        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('qr_token_hash', $tokenHash)
            ->first();

        if (! $invitee) {
            return view('gate.invalid', [
                'message' => 'Invalid or unknown QR code.',
            ]);
        }

        if (($invitee->card_status ?? 'active') === 'blocked') {
            return view('gate.invalid', [
                'message' => 'This invitation card is blocked.',
            ]);
        }

        $allowedCardStatuses = ['active', 'generated', 'sent'];

        if (! in_array(($invitee->card_status ?? 'active'), $allowedCardStatuses, true)) {
            return view('gate.invalid', [
                'message' => 'This invitation card is not valid for check-in.',
            ]);
        }

        return view('gate.verify', compact('invitee', 'token'));
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

                if (($invitee->card_status ?? 'active') === 'blocked') {
                    throw new \RuntimeException('This invitation card is blocked.');
                }

                $allowedCardStatuses = ['active', 'generated', 'sent'];

                if (! in_array(($invitee->card_status ?? 'active'), $allowedCardStatuses, true)) {
                    throw new \RuntimeException('This invitation card is not valid for check-in.');
                }

                if ($invitee->remaining_guests <= 0) {
                    throw new \RuntimeException('Guest limit already reached.');
                }

                if ($guestsToCheckIn > $invitee->remaining_guests) {
                    throw new \RuntimeException('Only ' . $invitee->remaining_guests . ' guest(s) remaining.');
                }

                $previousCount = (int) $invitee->checked_in_count;
                $newCount = $previousCount + $guestsToCheckIn;
                $remainingGuests = max(0, $invitee->final_allowed_guests - $newCount);

                $invitee->checkIns()->create([
                    'event_id' => $invitee->event_id,
                    'checked_in_by' => Auth::id(),
                    'checkin_method' => 'qr_scan',
                    'guests_checked_in' => $guestsToCheckIn,
                    'previous_checked_in_count' => $previousCount,
                    'remaining_guests' => $remainingGuests,
                    'status' => 'success',
                    'remarks' => 'Checked in by QR code scan.',
                    'checked_in_at' => now(),
                ]);

                $invitee->update([
                    'checked_in_count' => $newCount,
                    'checked_in_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gate.verify.show', $token)
            ->with('success', $guestsToCheckIn . ' guest(s) checked in successfully.');
    }
}
