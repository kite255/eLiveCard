<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class PublicEventController extends Controller
{
    public function index(): View
    {
        $upcomingEvents = Event::query()
            ->publiclyVisible()
            ->where('status', '!=', Event::STATUS_COMPLETED)
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate(12, ['*'], 'upcoming_page');

        $completedEvents = Event::query()
            ->publiclyVisible()
            ->where(function ($query): void {
                $query
                    ->where('status', Event::STATUS_COMPLETED)
                    ->orWhereDate('event_date', '<', now()->toDateString());
            })
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->paginate(12, ['*'], 'completed_page');

        return view('pages.events', compact(
            'upcomingEvents',
            'completedEvents'
        ));
    }

    public function show(Event $event): View
    {
        abort_unless(
            $event->canBeShownPublicly(),
            404
        );

        return view('pages.event-details', compact('event'));
    }
}
