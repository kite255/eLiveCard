<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Invitee;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GateCheckInController extends Controller
{
    /**
     * Display the professional gate check-in page.
     */
    public function show(Event $event)
    {
        $recentCheckIns = Invitee::query()
            ->with('cardType')
            ->where('event_id', $event->id)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('checked_in_at')
                    ->orWhere('checked_in_count', '>', 0);
            })
            ->latest('checked_in_at')
            ->limit(10)
            ->get();

        return view('gate.check-in', [
            'event' => $event,
            'recentCheckIns' => $recentCheckIns,
        ]);
    }

    /**
     * Verify scanned QR code or manual search value.
     *
     * Accepts:
     * - scanned_value from QR scanner
     * - search from manual search form
     * - serial number
     * - phone number
     * - invitee name
     * - short code
     * - raw QR token
     * - full /gate/verify/{token} URL
     * - full /i/{shortCode} URL
     */
    public function verify(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'scanned_value' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $rawValue = trim((string) ($validated['scanned_value'] ?? $validated['search'] ?? ''));

        if ($rawValue === '') {
            return response()->json([
                'status' => 'error',
                'title' => 'Search Required',
                'message' => 'Please scan a QR code or enter serial number, phone number, name, or short code.',
            ], 422);
        }

        $searchValue = $this->extractSearchValue($rawValue);

        $invitee = $this->findInvitee($event, $searchValue);

        if (! $invitee) {
            $otherEventInvitee = $this->findInviteeInAnotherEvent(
                currentEvent: $event,
                searchValue: $searchValue,
            );

            if ($otherEventInvitee) {
                return response()->json([
                    'status' => 'error',
                    'title' => 'Wrong Event',
                    'message' => 'This invitation belongs to '
                        .($otherEventInvitee->event?->title ?? 'another event')
                        .'. Open the correct event scanner before checking in.',
                    'other_event' => [
                        'id' => $otherEventInvitee->event_id,
                        'title' => $otherEventInvitee->event?->title
                            ?? $otherEventInvitee->event?->name
                            ?? 'Another event',
                        'date' => $otherEventInvitee->event?->event_date
                            ? $otherEventInvitee->event->event_date->format('d M Y')
                            : null,
                        'venue' => $otherEventInvitee->event?->venue_name
                            ?? $otherEventInvitee->event?->venue
                            ?? null,
                    ],
                ], 422);
            }

            return response()->json([
                'status' => 'error',
                'title' => 'Invitee Not Found',
                'message' => 'No matching invitation was found for this event.',
            ], 404);
        }

        $validationMessage = $this->validateInviteeForGate($invitee);

        $allowedGuests = $this->allowedGuests($invitee);
        $confirmedGuests = $this->confirmedGuests($invitee);
        $gateLimit = $this->gateGuestLimit($invitee);
        $checkedInCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max($gateLimit - $checkedInCount, 0);

        if ($validationMessage) {
            return response()->json([
                'status' => 'error',
                'title' => 'Check-in Not Allowed',
                'message' => $validationMessage,
                'invitee' => $this->inviteePayload(
                    invitee: $invitee,
                    allowedGuests: $allowedGuests,
                    confirmedGuests: $confirmedGuests,
                    gateLimit: $gateLimit,
                    checkedInCount: $checkedInCount,
                    remainingGuests: $remainingGuests
                ),
            ], 422);
        }

        if ($remainingGuests <= 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Already Checked In',
                'message' => 'This card has already used all allowed guest entries.',
                'invitee' => $this->inviteePayload(
                    invitee: $invitee,
                    allowedGuests: $allowedGuests,
                    confirmedGuests: $confirmedGuests,
                    gateLimit: $gateLimit,
                    checkedInCount: $checkedInCount,
                    remainingGuests: $remainingGuests
                ),
            ]);
        }

        $verificationMessage = match ($invitee->rsvp_status ?? 'pending') {
            'attending', 'confirmed', 'yes' =>
                'Attendance was confirmed. Select the number of guests entering now.',
            'not_attending', 'declined' =>
                'This invitee previously declined attendance, but the invitation is valid. Confirm before admitting guests.',
            default =>
                'Attendance was not confirmed, but the invitation is valid. Select the number of guests entering now.',
        };

        return response()->json([
            'status' => 'success',
            'title' => 'Valid Card',
            'message' => $verificationMessage,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->event_date?->format('d M Y'),
                'venue' => $event->venue_name ?? $event->venue ?? null,
            ],
            'invitee' => $this->inviteePayload(
                invitee: $invitee,
                allowedGuests: $allowedGuests,
                confirmedGuests: $confirmedGuests,
                gateLimit: $gateLimit,
                checkedInCount: $checkedInCount,
                remainingGuests: $remainingGuests
            ),
        ]);
    }

    /**
     * Confirm invitee check-in and enforce the invitation guest limit.
     */
    public function confirm(
        Request $request,
        Event $event,
        CheckInService $checkInService
    ): JsonResponse {
        $validated = $request->validate([
            'invitee_id' => ['required', 'integer'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'checkin_method' => [
                'nullable',
                'string',
                'in:qr,manual,serial,phone,name,gate_scanner',
            ],
        ]);

        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $event->id)
            ->whereKey($validated['invitee_id'])
            ->first();

        if (! $invitee) {
            return response()->json([
                'status' => 'error',
                'title' => 'Invitee Not Found',
                'message' => 'This invitee does not belong to the selected event.',
            ], 404);
        }

        $validationMessage = $this->validateInviteeForGate($invitee);

        $allowedGuests = $this->allowedGuests($invitee);
        $confirmedGuests = $this->confirmedGuests($invitee);
        $gateLimit = $this->gateGuestLimit($invitee);
        $checkedInCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max($gateLimit - $checkedInCount, 0);
        $guestCount = (int) $validated['guest_count'];

        if ($validationMessage) {
            return response()->json([
                'status' => 'error',
                'title' => 'Check-in Not Allowed',
                'message' => $validationMessage,
                'invitee' => $this->inviteePayload(
                    invitee: $invitee,
                    allowedGuests: $allowedGuests,
                    confirmedGuests: $confirmedGuests,
                    gateLimit: $gateLimit,
                    checkedInCount: $checkedInCount,
                    remainingGuests: $remainingGuests,
                ),
            ], 422);
        }

        if ($remainingGuests <= 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Already Checked In',
                'message' => 'This card has already used all allowed guest entries.',
                'invitee' => $this->inviteePayload(
                    invitee: $invitee,
                    allowedGuests: $allowedGuests,
                    confirmedGuests: $confirmedGuests,
                    gateLimit: $gateLimit,
                    checkedInCount: $checkedInCount,
                    remainingGuests: $remainingGuests,
                ),
            ], 422);
        }

        if ($guestCount > $remainingGuests) {
            return response()->json([
                'status' => 'error',
                'title' => 'Guest Limit Exceeded',
                'message' => "Only {$remainingGuests} guest(s) remaining for this card.",
                'invitee' => $this->inviteePayload(
                    invitee: $invitee,
                    allowedGuests: $allowedGuests,
                    confirmedGuests: $confirmedGuests,
                    gateLimit: $gateLimit,
                    checkedInCount: $checkedInCount,
                    remainingGuests: $remainingGuests,
                ),
            ], 422);
        }

        $method = $validated['checkin_method'] ?? CheckIn::METHOD_QR;

        $result = $checkInService->checkIn(
            invitee: $invitee,
            guestsCount: $guestCount,
            user: $request->user(),
            method: $method,
            expectedEventId: (int) $event->id,
        );

        $invitee->refresh()->loadMissing('cardType');

        $newCheckedInCount = (int) ($invitee->checked_in_count ?? 0);
        $newRemainingGuests = max($gateLimit - $newCheckedInCount, 0);

        $inviteePayload = $this->inviteePayload(
            invitee: $invitee,
            allowedGuests: $allowedGuests,
            confirmedGuests: $confirmedGuests,
            gateLimit: $gateLimit,
            checkedInCount: $newCheckedInCount,
            remainingGuests: $newRemainingGuests,
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'status' => $this->responseStatusForServiceResult($result),
                'title' => $result['title'] ?? 'Check-in Failed',
                'message' => $result['message'] ?? 'The invitee could not be checked in.',
                'invitee' => $inviteePayload,
            ], $this->responseCodeForServiceResult($result));
        }

        return response()->json([
            'status' => 'success',
            'title' => $result['title'] ?? 'Check-in Successful',
            'message' => "{$invitee->name} has been checked in successfully.",
            'check_in_id' => $result['check_in_id'] ?? null,
            'success_message' => [
                'heading' => 'Check-in Successful',
                'body' => "{$guestCount} guest(s) checked in successfully.",
                'invitee_name' => $invitee->name,
                'card_type' => $invitee->cardType?->name ?? 'N/A',
                'rsvp_status' => $this->formatStatus(
                    $invitee->rsvp_status ?? 'pending',
                ),
                'confirmed_guests' => $confirmedGuests,
                'guests_checked_in_now' => $guestCount,
                'total_checked_in' => $newCheckedInCount,
                'allowed_guests' => $allowedGuests,
                'gate_limit' => $gateLimit,
                'remaining_guests' => $newRemainingGuests,
                'table_number' => $invitee->table_number ?? 'N/A',
                'category' => $invitee->category ?? 'N/A',
                'serial_number' => $invitee->serial_number ?? 'N/A',
                'checked_in_time' => optional(
                    $invitee->checked_in_at,
                )->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
            ],
            'invitee' => $inviteePayload,
        ]);
    }

    private function responseStatusForServiceResult(array $result): string
    {
        $title = strtolower((string) ($result['title'] ?? ''));

        return str_contains($title, 'limit')
            || str_contains($title, 'already')
            || str_contains($title, 'duplicate')
                ? 'warning'
                : 'error';
    }

    private function responseCodeForServiceResult(array $result): int
    {
        return $this->responseStatusForServiceResult($result) === 'warning'
            ? 409
            : 422;
    }

    /**
     * Find invitee by QR token, QR hash, serial, phone, name, or short code.
     */
    private function findInvitee(Event $event, string $searchValue): ?Invitee
    {
        $searchValue = trim($searchValue);
        $lowerSearchValue = mb_strtolower($searchValue);
        $tokenHash = hash('sha256', $searchValue);
        $normalizedPhone = preg_replace('/\D+/', '', $searchValue);

        $serialLike = '%' . $lowerSearchValue . '%';
        $nameLike = '%' . $lowerSearchValue . '%';
        $phoneLike = $normalizedPhone !== ''
            ? '%' . $normalizedPhone . '%'
            : '__NO_PHONE_MATCH__';

        return Invitee::query()
            ->with('cardType')
            ->where('event_id', $event->id)
            ->where(function ($query) use (
                $searchValue,
                $tokenHash,
                $normalizedPhone,
                $serialLike,
                $nameLike,
                $phoneLike
            ) {
                $query
                    /*
                     |--------------------------------------------------------------------------
                     | Exact matches
                     |--------------------------------------------------------------------------
                     | Best and safest matches. These should always win.
                     */
                    ->where('serial_number', $searchValue)
                    ->orWhere('short_code', $searchValue)
                    ->orWhere('qr_token', $searchValue)
                    ->orWhere('qr_token_hash', $tokenHash)
                    ->orWhere('phone', $searchValue)

                    /*
                     |--------------------------------------------------------------------------
                     | Partial serial search
                     |--------------------------------------------------------------------------
                     | Allows gate users to type only the last serial code.
                     | Example:
                     | GL2WFB finds ELV-2026-GL2WFB
                     */
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', [$serialLike])

                    /*
                     |--------------------------------------------------------------------------
                     | Short code fallback
                     |--------------------------------------------------------------------------
                     | Allows partial short-code search when the invitee gives only part
                     | of the private invitee code.
                     */
                    ->orWhereRaw('LOWER(short_code) LIKE ?', [$serialLike])

                    /*
                     |--------------------------------------------------------------------------
                     | Name fallback
                     |--------------------------------------------------------------------------
                     | Name search should be last because names can be duplicated.
                     */
                    ->orWhereRaw('LOWER(name) LIKE ?', [$nameLike]);

                if ($normalizedPhone !== '' && $normalizedPhone !== $searchValue) {
                    $query->orWhere('phone', $normalizedPhone);
                }

                if ($normalizedPhone !== '') {
                    $query->orWhere('phone', 'like', $phoneLike);
                }
            })
            ->orderByRaw(
                "
                CASE
                    WHEN serial_number = ? THEN 1
                    WHEN short_code = ? THEN 2
                    WHEN qr_token = ? THEN 3
                    WHEN qr_token_hash = ? THEN 4
                    WHEN phone = ? THEN 5
                    WHEN LOWER(serial_number) LIKE ? THEN 6
                    WHEN LOWER(short_code) LIKE ? THEN 7
                    WHEN phone LIKE ? THEN 8
                    WHEN LOWER(name) LIKE ? THEN 9
                    ELSE 99
                END
                ",
                [
                    $searchValue,
                    $searchValue,
                    $searchValue,
                    $tokenHash,
                    $searchValue,
                    $serialLike,
                    $serialLike,
                    $phoneLike,
                    $nameLike,
                ]
            )
            ->first();
    }

    /**
     * Detect an exact invitation identifier that belongs to another event.
     *
     * Name and phone are intentionally excluded because one person may
     * legitimately have invitations for multiple events.
     */
    private function findInviteeInAnotherEvent(
        Event $currentEvent,
        string $searchValue
    ): ?Invitee {
        $searchValue = trim($searchValue);

        if ($searchValue === '') {
            return null;
        }

        $tokenHash = hash('sha256', $searchValue);

        return Invitee::query()
            ->with('event')
            ->where('event_id', '!=', $currentEvent->id)
            ->where(function ($query) use ($searchValue, $tokenHash): void {
                $query
                    ->where('qr_token', $searchValue)
                    ->orWhere('qr_token_hash', $tokenHash)
                    ->orWhere('serial_number', $searchValue)
                    ->orWhere('short_code', $searchValue);
            })
            ->first();
    }

    /**
     * Extract token, short code, or serial from scanned QR/manual content.
     */
    private function extractSearchValue(string $value): string
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH);

            if ($path) {
                $segments = array_values(array_filter(explode('/', $path)));

                if (! empty($segments)) {
                    return trim((string) end($segments));
                }
            }
        }

        return $value;
    }

    private function validateInviteeForGate(Invitee $invitee): ?string
    {
        $cardStatus = $invitee->card_status ?? 'active';

        if ($cardStatus === 'blocked') {
            return 'This invitation card is blocked.';
        }

        if ($cardStatus === 'cancelled') {
            return 'This invitation card is cancelled.';
        }

        $allowedCardStatuses = [
            'active',
            'generated',
            'sent',
        ];

        if (! in_array($cardStatus, $allowedCardStatuses, true)) {
            return 'This invitation card is not valid for check-in.';
        }

        if ($this->gateGuestLimit($invitee) <= 0) {
            return 'No guests are allowed for check-in on this invitation.';
        }

        return null;
    }

    /**
     * Original invitation/card allowed guest count.
     */
    private function allowedGuests(Invitee $invitee): int
    {
        if (isset($invitee->final_allowed_guests) && (int) $invitee->final_allowed_guests > 0) {
            return (int) $invitee->final_allowed_guests;
        }

        if ((int) ($invitee->allowed_guests ?? 0) > 0) {
            return (int) $invitee->allowed_guests;
        }

        if ((int) ($invitee->cardType?->allowed_guests ?? 0) > 0) {
            return (int) $invitee->cardType->allowed_guests;
        }

        if ((int) ($invitee->cardType?->allowed_people ?? 0) > 0) {
            return (int) $invitee->cardType->allowed_people;
        }

        return 1;
    }

    private function confirmedGuests(Invitee $invitee): int
    {
        return max(0, (int) ($invitee->confirmed_guests ?? 0));
    }

    /**
     * Actual guest limit to enforce at the gate.
     */
    private function gateGuestLimit(Invitee $invitee): int
    {
        return $this->allowedGuests($invitee);
    }

    /**
     * Format invitee response for frontend.
     */
    private function inviteePayload(
        Invitee $invitee,
        int $allowedGuests,
        int $confirmedGuests,
        int $gateLimit,
        int $checkedInCount,
        int $remainingGuests
    ): array {
        return [
            'id' => $invitee->id,
            'name' => $invitee->name,
            'phone' => $invitee->phone,
            'serial_number' => $invitee->serial_number,
            'short_code' => $invitee->short_code,
            'card_type' => $invitee->cardType?->name ?? 'N/A',
            'card_status' => $this->formatStatus($invitee->card_status ?? 'active'),
            'rsvp_status' => $invitee->rsvp_status ?? 'pending',
            'rsvp_status_label' => $this->formatStatus($invitee->rsvp_status ?? 'pending'),
            'confirmed_guests' => $confirmedGuests,
            'allowed_guests' => $allowedGuests,
            'gate_limit' => $gateLimit,
            'checked_in_count' => $checkedInCount,
            'remaining_guests' => $remainingGuests,
            'table_number' => $invitee->table_number ?? 'N/A',
            'category' => $invitee->category ?? 'N/A',
            'checked_in_at' => $invitee->checked_in_at
                ? $invitee->checked_in_at->format('d M Y, h:i A')
                : null,
            'check_in_status' => $this->hasColumn('check_in_status')
                ? ($invitee->check_in_status ?? null)
                : null,
        ];
    }

    private function formatStatus(?string $status): string
    {
        return $status
            ? str($status)->replace('_', ' ')->title()->toString()
            : 'Pending';
    }

    /**
     * Safe check before setting optional columns.
     */
    private function hasColumn(string $column): bool
    {
        return Schema::hasColumn('invitees', $column);
    }
}
