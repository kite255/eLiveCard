<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('gate.verify', compact('invitee', 'token'));
    }

    public function checkIn(Request $request, string $token)
    {
        $request->validate([
            'guests_to_check_in' => ['required', 'integer', 'min:1'],
        ]);

        $tokenHash = hash('sha256', $token);

        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('qr_token_hash', $tokenHash)
            ->first();

        if (! $invitee) {
            return back()->with('error', 'Invalid QR code.');
        }

        $invitee->refresh();

        $guestsToCheckIn = (int) $request->guests_to_check_in;

        if ($invitee->card_status === 'blocked') {
            return back()->with('error', 'This invitation card is blocked.');
        }

        if ($invitee->remaining_guests <= 0) {
            return back()->with('error', 'Guest limit already reached.');
        }

        if ($guestsToCheckIn > $invitee->remaining_guests) {
            return back()->with('error', 'Only ' . $invitee->remaining_guests . ' guest(s) remaining.');
        }

        $previousCount = $invitee->checked_in_count;
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

        return redirect()
            ->route('gate.verify.show', $token)
            ->with('success', $guestsToCheckIn . ' guest(s) checked in successfully.');
    }
}