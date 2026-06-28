<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->where(function ($query) {
                $query->whereNotNull('checked_in_at')
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
            return response()->json([
                'status' => 'error',
                'title' => 'Invitee Not Found',
                'message' => 'No invitee was found for this event using that QR, serial number, phone number, name, or short code.',
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

        return response()->json([
            'status' => 'success',
            'title' => 'Valid Card',
            'message' => 'Invitee found. You can proceed with check-in.',
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
     * Confirm invitee check-in and enforce RSVP-based guest limit.
     */
    public function confirm(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'invitee_id' => ['required', 'integer'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($validated, $event) {
            $invitee = Invitee::query()
                ->with('cardType')
                ->where('event_id', $event->id)
                ->where('id', $validated['invitee_id'])
                ->lockForUpdate()
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
                        remainingGuests: $remainingGuests
                    ),
                ], 422);
            }

            $invitee->checked_in_count = $checkedInCount + $guestCount;
            $invitee->checked_in_at = $invitee->checked_in_at ?? now();

            $newCheckedInCount = (int) $invitee->checked_in_count;
            $newRemainingGuests = max($gateLimit - $newCheckedInCount, 0);

            if ($this->hasColumn('check_in_status')) {
                $invitee->check_in_status = $newRemainingGuests <= 0
                    ? 'checked_in'
                    : 'partially_checked_in';
            }

            if ($this->hasColumn('checked_in_by') && auth()->check()) {
                $invitee->checked_in_by = auth()->id();
            }

            $invitee->save();

            $inviteePayload = $this->inviteePayload(
                invitee: $invitee,
                allowedGuests: $allowedGuests,
                confirmedGuests: $confirmedGuests,
                gateLimit: $gateLimit,
                checkedInCount: $newCheckedInCount,
                remainingGuests: $newRemainingGuests
            );

            return response()->json([
                'status' => 'success',
                'title' => 'Check-in Successful',
                'message' => "{$invitee->name} has been checked in successfully.",
                'success_message' => [
                    'heading' => 'Check-in Successful',
                    'body' => "{$guestCount} guest(s) checked in successfully.",
                    'invitee_name' => $invitee->name,
                    'card_type' => $invitee->cardType?->name ?? 'N/A',
                    'rsvp_status' => $this->formatStatus($invitee->rsvp_status ?? 'pending'),
                    'confirmed_guests' => $confirmedGuests,
                    'guests_checked_in_now' => $guestCount,
                    'total_checked_in' => $newCheckedInCount,
                    'allowed_guests' => $allowedGuests,
                    'gate_limit' => $gateLimit,
                    'remaining_guests' => $newRemainingGuests,
                    'table_number' => $invitee->table_number ?? 'N/A',
                    'category' => $invitee->category ?? 'N/A',
                    'checked_in_time' => now()->format('d M Y, h:i A'),
                ],
                'invitee' => $inviteePayload,
            ]);
        });
    }

    /**
     * Find invitee by QR token, QR hash, serial, phone, name, or short code.
     */
    private function findInvitee(Event $event, string $searchValue): ?Invitee
    {
        $searchValue = trim($searchValue);
        $tokenHash = hash('sha256', $searchValue);
        $normalizedPhone = preg_replace('/\D+/', '', $searchValue);

        return Invitee::query()
            ->with('cardType')
            ->where('event_id', $event->id)
            ->where(function ($query) use ($searchValue, $tokenHash, $normalizedPhone) {
                $query
                    ->where('serial_number', $searchValue)
                    ->orWhere('short_code', $searchValue)
                    ->orWhere('qr_token', $searchValue)
                    ->orWhere('qr_token_hash', $tokenHash)
                    ->orWhere('phone', $searchValue)
                    ->orWhere('name', 'like', '%' . $searchValue . '%');

                if ($normalizedPhone !== '' && $normalizedPhone !== $searchValue) {
                    $query->orWhere('phone', $normalizedPhone);
                }

                if ($normalizedPhone !== '') {
                    $query->orWhere('phone', 'like', '%' . $normalizedPhone . '%');
                }
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

        if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
            return 'This invitee responded that they will not attend. Please contact the event manager before allowing check-in.';
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
        $allowedGuests = $this->allowedGuests($invitee);
        $confirmedGuests = $this->confirmedGuests($invitee);

        if ($invitee->rsvp_status === 'attending' && $confirmedGuests > 0) {
            return min($confirmedGuests, $allowedGuests);
        }

        if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
            return 0;
        }

        return $allowedGuests;
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
