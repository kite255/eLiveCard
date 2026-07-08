<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class InviteeLocationController extends Controller
{
    public function __invoke(string $shortCode): RedirectResponse|Response
    {
        $invitee = Invitee::query()
            ->with('event')
            ->where('short_code', $shortCode)
            ->first();

        if (! $invitee || ! $invitee->event) {
            return response('Location not found.', 404);
        }

        $event = $invitee->event;

        if (filled($event->google_maps_link)) {
            return redirect()->away($event->google_maps_link);
        }

        $query = trim(($event->venue_name ?? '') . ' ' . ($event->venue_address ?? ''));

        if (filled($query)) {
            return redirect()->away(
                'https://www.google.com/maps/search/?api=1&query=' . urlencode($query)
            );
        }

        return response('Location is not available for this event.', 404);
    }
}