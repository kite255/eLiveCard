<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;

class PublicEventsApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $events = Event::query()
            ->publiclyVisible()
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Event $event): array {
                $status = match ($event->display_status) {
                    Event::STATUS_ACTIVE => 'live',
                    Event::STATUS_COMPLETED => 'past',
                    default => 'upcoming',
                };

                return [
                    'id' => 'digital-'.$event->getKey(),
                    'source' => 'digital',
                    'kind' => 'invitation',
                    'name' => $event->display_name,
                    'event_type' => $event->event_type_display,
                    'summary' => $event->public_summary,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                    'venue' => $event->venue_name,
                    'venue_address' => $event->venue_address,
                    'image_url' => $event->cover_image_url,
                    'status' => $status,
                    'url' => route('events.show', ['event' => $event->getKey()]),
                    'registration_url' => null,
                    'ticket_url' => null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $events,
            'meta' => [
                'source' => 'digital.elive.co.tz',
                'count' => $events->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
