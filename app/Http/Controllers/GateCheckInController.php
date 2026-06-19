<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GateCheckInController extends Controller
{
    /**
     * Display the professional gate check-in page.
     */
    public function show(Event $event)
    {
        $recentCheckIns = Invitee::query()
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
     */
    public function verify(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'scanned_value' => ['required', 'string', 'max:255'],
        ]);

        $value = trim($validated['scanned_value']);

        /*
         * QR codes may contain:
         * 1. Raw token
         * 2. Serial number
         * 3. Short code
         * 4. Full URL such as https://digital.elive.co.tz/gate/verify/TOKEN
         * 5. Full invitee URL such as https://digital.elive.co.tz/i/SHORTCODE
         */
        $searchValue = $this->extractSearchValue($value);

        $invitee = Invitee::query()
            ->with('cardType')
            ->where('event_id', $event->id)
            ->where(function ($query) use ($searchValue) {
                $query->where('serial_number', $searchValue)
                    ->orWhere('short_code', $searchValue)
                    ->orWhere('qr_token', $searchValue)
                    ->orWhere('phone', $searchValue)
                    ->orWhere('name', 'like', '%' . $searchValue . '%');
            })
            ->first();

        if (! $invitee) {
            return response()->json([
                'status' => 'error',
                'title' => 'Invalid Card',
                'message' => 'No invitee was found for this event.',
            ], 404);
        }

        $allowedGuests = $this->allowedGuests($invitee);
        $checkedInCount = (int) ($invitee->checked_in_count ?? 0);
        $remainingGuests = max($allowedGuests - $checkedInCount, 0);

        if ($remainingGuests <= 0) {
            return response()->json([
                'status' => 'warning',
                'title' => 'Already Checked In',
                'message' => 'This card has already used all allowed guest entries.',
                'invitee' => $this->inviteePayload(
                    invitee: $invitee,
                    allowedGuests: $allowedGuests,
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
                checkedInCount: $checkedInCount,
                remainingGuests: $remainingGuests
            ),
        ]);
    }

    /**
     * Confirm invitee check-in and enforce guest limit.
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

            $allowedGuests = $this->allowedGuests($invitee);
            $checkedInCount = (int) ($invitee->checked_in_count ?? 0);
            $remainingGuests = max($allowedGuests - $checkedInCount, 0);
            $guestCount = (int) $validated['guest_count'];

            if ($remainingGuests <= 0) {
                return response()->json([
                    'status' => 'warning',
                    'title' => 'Already Checked In',
                    'message' => 'This card has already used all allowed guest entries.',
                ], 422);
            }

            if ($guestCount > $remainingGuests) {
                return response()->json([
                    'status' => 'error',
                    'title' => 'Guest Limit Exceeded',
                    'message' => "Only {$remainingGuests} guest(s) remaining for this card.",
                ], 422);
            }

            $invitee->checked_in_count = $checkedInCount + $guestCount;
            $invitee->checked_in_at = $invitee->checked_in_at ?? now();

            if ($this->hasColumn($invitee, 'check_in_status')) {
                $invitee->check_in_status = 'checked_in';
            }

            $invitee->save();

            $newRemainingGuests = max($allowedGuests - (int) $invitee->checked_in_count, 0);

            return response()->json([
                'status' => 'success',
                'title' => 'Check-in Successful',
                'message' => "{$guestCount} guest(s) checked in successfully.",
                'checked_in_count' => (int) $invitee->checked_in_count,
                'remaining_guests' => $newRemainingGuests,
            ]);
        });
    }

    /**
     * Extract token, short code, or serial from scanned QR content.
     */
    private function extractSearchValue(string $value): string
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH);

            if ($path) {
                $segments = array_values(array_filter(explode('/', $path)));

                if (! empty($segments)) {
                    return end($segments);
                }
            }
        }

        return $value;
    }

    /**
     * Resolve allowed guest count from invitee or card type.
     */
    private function allowedGuests(Invitee $invitee): int
    {
        $allowedGuests = $invitee->allowed_guests
            ?? $invitee->cardType?->allowed_people
            ?? $invitee->cardType?->allowed_guests
            ?? 1;

        return max((int) $allowedGuests, 1);
    }

    /**
     * Format invitee response for frontend.
     */
    private function inviteePayload(
        Invitee $invitee,
        int $allowedGuests,
        int $checkedInCount,
        int $remainingGuests
    ): array {
        return [
            'id' => $invitee->id,
            'name' => $invitee->name,
            'phone' => $invitee->phone,
            'serial_number' => $invitee->serial_number,
            'short_code' => $invitee->short_code,
            'card_type' => $invitee->cardType?->name,
            'allowed_guests' => $allowedGuests,
            'checked_in_count' => $checkedInCount,
            'remaining_guests' => $remainingGuests,
            'table_number' => $invitee->table_number,
            'category' => $invitee->category,
        ];
    }

    /**
     * Safe check before setting optional columns.
     */
    private function hasColumn(Invitee $invitee, string $column): bool
    {
        return array_key_exists($column, $invitee->getAttributes())
            || in_array($column, $invitee->getFillable(), true);
    }
}