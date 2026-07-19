<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicRsvpReportController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $event = Event::query()
            ->where('rsvp_share_token', hash('sha256', $token))
            ->where('rsvp_share_enabled', true)
            ->firstOrFail();

        abort_if(
            $event->rsvpShareHasExpired(),
            410,
            'This RSVP report link has expired.'
        );

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $invitees = $event->invitees()
            ->with('cardType')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                })
            )
            ->when(
                $status !== '',
                fn ($query) => $query->where('rsvp_status', $status)
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $total = $event->invitees()->count();

        $attending = $event->invitees()
            ->where('rsvp_status', 'attending')
            ->count();

        $notAttending = $event->invitees()
            ->whereIn('rsvp_status', [
                'not_attending',
                'declined',
            ])
            ->count();

        $pending = max(
            $total - $attending - $notAttending,
            0
        );

        $responseRate = $total > 0
            ? round(
                (($attending + $notAttending) / $total) * 100
            )
            : 0;

        $confirmedGuests = (int) $event->invitees()
            ->where('rsvp_status', 'attending')
            ->sum('confirmed_guests');

        return view('public.rsvp-report', compact(
            'event',
            'invitees',
            'total',
            'attending',
            'notAttending',
            'pending',
            'responseRate',
            'confirmedGuests',
            'search',
            'status',
        ));
    }
}