<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use Illuminate\Http\Request;

class InviteePageController extends Controller
{
    public function show(string $shortCode)
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_if(
            $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE,
            403,
            'This invitation is not active.'
        );

        return view('invitees.show', compact('invitee'));
    }

    public function rsvp(Request $request, string $shortCode)
    {
        $request->validate([
            'status' => ['required', 'in:attending,not_attending'],
        ]);

        $invitee = Invitee::query()
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_if(
            $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE,
            403,
            'This invitation is not active.'
        );

        $invitee->update([
            'rsvp_status' => $request->status,
            'rsvp_confirmed_at' => now(),
        ]);

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', 'Thank you. Your RSVP response has been recorded successfully.');
    }
}