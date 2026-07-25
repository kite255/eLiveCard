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
        $invitee = $this->findInvitee($token);

        return view('rsvp.show', compact('invitee'));
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $invitee = $this->findInvitee($token);

        /*
         * Support both form field names:
         * - status (used by the private invitee page)
         * - rsvp_status (used by the standalone RSVP page)
         */
        $request->merge([
            'rsvp_status' => $request->input(
                'rsvp_status',
                $request->input('status')
            ),
        ]);

        $validated = $request->validate([
            'rsvp_status' => [
                'required',
                'in:attending,not_attending,pending',
            ],
        ]);

        $status = (string) $validated['rsvp_status'];
        $allowedGuests = $this->allowedGuests($invitee);

        $confirmedGuests = match ($status) {
            Invitee::RSVP_ATTENDING, 'attending' => $allowedGuests,
            Invitee::RSVP_NOT_ATTENDING, 'not_attending' => 0,
            default => null,
        };

        $invitee->forceFill([
            'rsvp_status' => $status,
            'confirmed_guests' => $confirmedGuests,
            'rsvp_confirmed_at' => $status === 'pending'
                ? null
                : now(),
        ])->save();

        $message = match ($status) {
            'attending' => "Attendance confirmed for all {$allowedGuests} allowed guest(s).",
            'not_attending' => 'Your response has been recorded as not attending.',
            default => 'Your RSVP remains pending. You can confirm later.',
        };

        $redirectToken = $invitee->rsvp_token
            ?: $invitee->short_code
            ?: $token;

        if (request()->routeIs('invitee.rsvp')) {
            return back()->with('success', $message);
        }

        return redirect()
            ->route('rsvp.thank-you', $redirectToken)
            ->with('success', $message);
    }

    public function thankYou(string $token): View
    {
        $invitee = $this->findInvitee($token);

        return view('rsvp.thank-you', compact('invitee'));
    }

    private function findInvitee(string $token): Invitee
    {
        $token = trim($token);

        abort_if($token === '', 404);

        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where(function ($query) use ($token): void {
                $query->where('rsvp_token', $token)
                    ->orWhere('short_code', $token);
            })
            ->firstOrFail();
    }

    private function allowedGuests(Invitee $invitee): int
    {
        return max(1, (int) (
            $invitee->final_allowed_guests
            ?: $invitee->allowed_guests
            ?: $invitee->cardType?->allowed_guests
            ?: $invitee->cardType?->allowed_people
            ?: 1
        ));
    }
}
