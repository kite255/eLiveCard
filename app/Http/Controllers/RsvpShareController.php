<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RsvpShareController extends Controller
{
    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        return response()->json([
            'enabled' => $event->hasValidRsvpShareLink(),
            'url' => $event->rsvp_share_url,
            'expires_at' => $event->rsvp_share_expires_at?->toIso8601String(),
            'show_phone' => (bool) $event->rsvp_share_show_phone,
        ]);
    }

    public function generate(
        Request $request,
        Event $event
    ): JsonResponse {
        $this->authorizeEvent($request, $event);

        $validated = $request->validate([
            'expires_in_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],
            'show_phone' => [
                'nullable',
                'boolean',
            ],
            'regenerate' => [
                'nullable',
                'boolean',
            ],
        ]);

        $expiresAt = filled($validated['expires_in_days'] ?? null)
            ? now()->addDays((int) $validated['expires_in_days'])
            : null;

        $showPhone = (bool) ($validated['show_phone'] ?? false);

        $url = ($validated['regenerate'] ?? false)
            ? $event->generateRsvpShareLink(
                $expiresAt,
                $showPhone
            )
            : $event->ensureRsvpShareLink(
                $expiresAt,
                $showPhone
            );

        if (! ($validated['regenerate'] ?? false)) {
            $event->forceFill([
                'rsvp_share_show_phone' => $showPhone,
                'rsvp_share_expires_at' => $expiresAt,
                'rsvp_share_enabled' => true,
            ])->save();
        }

        return response()->json([
            'message' => 'Client RSVP link is ready.',
            'url' => $url,
            'expires_at' => $expiresAt?->toIso8601String(),
            'show_phone' => $showPhone,
        ]);
    }

    public function disable(
        Request $request,
        Event $event
    ): JsonResponse {
        $this->authorizeEvent($request, $event);

        $event->disableRsvpShareLink();

        return response()->json([
            'message' => 'Client RSVP link disabled.',
        ]);
    }

    private function authorizeEvent(
        Request $request,
        Event $event
    ): void {
        abort_unless(
            $event->canBeManagedBy($request->user()),
            403
        );
    }
}
