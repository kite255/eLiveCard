<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RsvpController extends Controller
{
    public function show(string $token): View
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('rsvp_token', $token)
            ->firstOrFail();

        return view('rsvp.show', compact('invitee'));
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('rsvp_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'rsvp_status' => ['required', 'in:attending,not_attending'],
            'confirmed_guests' => [
                'nullable',
                'integer',
                'min:1',
                'max:' . max(1, (int) $invitee->allowed_guests),
            ],
        ]);

        $status = $validated['rsvp_status'];

        $confirmedGuests = $status === Invitee::RSVP_ATTENDING
            ? (int) ($validated['confirmed_guests'] ?? 1)
            : 0;

        $invitee->update([
            'rsvp_status' => $status,
            'confirmed_guests' => $confirmedGuests,
            'rsvp_confirmed_at' => now(),
        ]);

        return redirect()
            ->route('rsvp.thank-you', $invitee->rsvp_token)
            ->with('success', 'Your RSVP response has been recorded.');
    }

    public function thankYou(string $token): View
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('rsvp_token', $token)
            ->firstOrFail();

        return view('rsvp.thank-you', compact('invitee'));
    }
}