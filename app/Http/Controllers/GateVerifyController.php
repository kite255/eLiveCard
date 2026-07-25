<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\Invitee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class GateVerifyController extends Controller
{
    /**
     * Display the QR verification and guest check-in page.
     */
    public function show(string $token): View
    {
        $invitee = $this->findInviteeByToken($token);

        if (! $invitee) {
            return view('gate.invalid', [
                'message' => 'Invalid or unknown QR code.',
            ]);
        }

        $this->authorizeGateAccess($invitee);

        $validationMessage = $this->validateInviteeForCheckIn($invitee);

        if ($validationMessage) {
            return view('gate.invalid', [
                'message' => $validationMessage,
                'invitee' => $invitee,
            ]);
        }

        $allowedGuests = $this->allowedGuests($invitee);
        $confirmedGuests = $this->confirmedGuests($invitee);
        $gateLimit = $this->gateGuestLimit($invitee);

        $checkedInCount = max(
            0,
            (int) ($invitee->checked_in_count ?? 0)
        );

        $remainingGuests = max(
            0,
            $gateLimit - $checkedInCount
        );

        return view('gate.verify', [
            'invitee' => $invitee,
            'token' => $token,
            'allowedGuests' => $allowedGuests,
            'confirmedGuests' => $confirmedGuests,
            'gateLimit' => $gateLimit,
            'checkedInCount' => $checkedInCount,
            'remainingGuests' => $remainingGuests,
        ]);
    }

    /**
     * Record a QR-based guest check-in.
     */
    public function checkIn(
        Request $request,
        string $token
    ): RedirectResponse {
        /*
        |--------------------------------------------------------------------------
        | Authorize before starting the transaction
        |--------------------------------------------------------------------------
        |
        | This gives unauthorized users the branded 403 page immediately and
        | prevents authorization exceptions from being converted into a generic
        | check-in error message.
        |
        */
        $inviteeForAuthorization = $this->findInviteeByToken($token);

        if (! $inviteeForAuthorization) {
            return back()->with(
                'error',
                'Invalid or unknown QR code.'
            );
        }

        $this->authorizeGateAccess($inviteeForAuthorization);

        $validated = $request->validate([
            'guests_to_check_in' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $guestsToCheckIn = (int) $validated['guests_to_check_in'];
        $tokenHash = hash('sha256', $token);

        try {
            $result = DB::transaction(function () use (
                $tokenHash,
                $guestsToCheckIn
            ): array {
                $invitee = Invitee::query()
                    ->with([
                        'event',
                        'cardType',
                    ])
                    ->where('qr_token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if (! $invitee) {
                    return [
                        'success' => false,
                        'message' => 'Invalid QR code.',
                    ];
                }

                /*
                 * Authorize again after locking the current database record.
                 * This protects against direct requests and assignment changes.
                 */
                $this->authorizeGateAccess($invitee);

                $validationMessage = $this->validateInviteeForCheckIn(
                    $invitee
                );

                if ($validationMessage) {
                    $this->recordFailedAttempt(
                        $invitee,
                        $validationMessage
                    );

                    return [
                        'success' => false,
                        'message' => $validationMessage,
                    ];
                }

                $gateLimit = $this->gateGuestLimit($invitee);

                $previousCount = max(
                    0,
                    (int) ($invitee->checked_in_count ?? 0)
                );

                $remainingBeforeCheckIn = max(
                    0,
                    $gateLimit - $previousCount
                );

                if ($remainingBeforeCheckIn <= 0) {
                    $message = 'Guest limit already reached.';

                    $this->recordDuplicateAttempt(
                        $invitee,
                        $message
                    );

                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }

                if ($guestsToCheckIn > $remainingBeforeCheckIn) {
                    $message = 'Only '
                        .$remainingBeforeCheckIn
                        .' guest(s) remaining.';

                    $this->recordFailedAttempt(
                        $invitee,
                        $message
                    );

                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }

                $newCount = $previousCount + $guestsToCheckIn;

                $remainingAfterCheckIn = max(
                    0,
                    $gateLimit - $newCount
                );

                $checkInStatus = $remainingAfterCheckIn === 0
                    ? CheckIn::STATUS_SUCCESS
                    : 'partial';

                $invitee->checkIns()->create([
                    'event_id' => $invitee->event_id,
                    'checked_in_by' => Auth::id(),
                    'checkin_method' => CheckIn::METHOD_QR,
                    'guests_checked_in' => $guestsToCheckIn,
                    'previous_checked_in_count' => $previousCount,
                    'remaining_guests' => $remainingAfterCheckIn,
                    'status' => $checkInStatus,
                    'remarks' => $this->successRemarks($invitee),
                    'checked_in_at' => now(),
                    'device_name' => request()->userAgent(),
                    'ip_address' => request()->ip(),
                ]);

                $invitee->forceFill([
                    'checked_in_count' => $newCount,
                    'check_in_status' => $remainingAfterCheckIn === 0
                        ? 'checked_in'
                        : 'partially_checked_in',
                    'checked_in_at' => now(),
                ])->save();

                return [
                    'success' => true,
                    'message' => $guestsToCheckIn
                        .' guest(s) checked in successfully.',
                ];
            }, 3);
        } catch (HttpExceptionInterface $exception) {
            /*
             * Preserve 403 and other intended HTTP responses so Laravel can
             * render resources/views/errors/403.blade.php.
             */
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Check-in could not be completed. Please try again.'
            );
        }

        if (! ($result['success'] ?? false)) {
            return back()->with(
                'error',
                (string) ($result['message'] ?? 'Check-in failed.')
            );
        }

        return redirect()
            ->route('gate.verify.show', [
                'token' => $token,
            ])
            ->with(
                'success',
                (string) $result['message']
            );
    }

    /**
     * Locate an invitee using the stored SHA-256 QR token hash.
     */
    private function findInviteeByToken(string $token): ?Invitee
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        return Invitee::query()
            ->with([
                'event',
                'cardType',
            ])
            ->where('qr_token_hash', $tokenHash)
            ->first();
    }

    /**
     * Ensure the authenticated user is allowed to scan for this event.
     */
    private function authorizeGateAccess(Invitee $invitee): void
    {
        $user = Auth::user();
        $event = $invitee->event;

        abort_unless(
            $user && $event,
            403,
            'You must sign in with an authorized event account.'
        );

        abort_unless(
            $user->canCheckInForEvent($event),
            403,
            'You are not assigned to manage check-in for this event.'
        );
    }

    /**
     * Validate the invitee and invitation before admission.
     */
    private function validateInviteeForCheckIn(
        Invitee $invitee
    ): ?string {
        if (! $invitee->event) {
            return 'The event connected to this invitation could not be found.';
        }

        $cardStatus = (string) (
            $invitee->card_status
            ?? Invitee::CARD_STATUS_ACTIVE
        );

        if ($cardStatus === Invitee::CARD_STATUS_BLOCKED) {
            return 'This invitation card is blocked.';
        }

        if ($cardStatus === Invitee::CARD_STATUS_CANCELLED) {
            return 'This invitation card is cancelled.';
        }

        $allowedCardStatuses = [
            Invitee::CARD_STATUS_ACTIVE,
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

    private function allowedGuests(Invitee $invitee): int
    {
        $finalAllowedGuests = (int) (
            $invitee->final_allowed_guests
            ?? 0
        );

        if ($finalAllowedGuests > 0) {
            return $finalAllowedGuests;
        }

        $inviteeAllowedGuests = (int) (
            $invitee->allowed_guests
            ?? 0
        );

        if ($inviteeAllowedGuests > 0) {
            return $inviteeAllowedGuests;
        }

        $cardTypeAllowedGuests = (int) (
            $invitee->cardType?->allowed_guests
            ?? 0
        );

        if ($cardTypeAllowedGuests > 0) {
            return $cardTypeAllowedGuests;
        }

        $cardTypeAllowedPeople = (int) (
            $invitee->cardType?->allowed_people
            ?? 0
        );

        if ($cardTypeAllowedPeople > 0) {
            return $cardTypeAllowedPeople;
        }

        return 1;
    }

    private function confirmedGuests(Invitee $invitee): int
    {
        return max(
            0,
            (int) ($invitee->confirmed_guests ?? 0)
        );
    }

    private function gateGuestLimit(Invitee $invitee): int
    {
        return $this->allowedGuests($invitee);
    }

    private function successRemarks(Invitee $invitee): string
    {
        return match ($invitee->rsvp_status) {
            Invitee::RSVP_ATTENDING, 'confirmed' =>
                'Checked in by QR code. RSVP was confirmed; invitation guest limit applied.',
            Invitee::RSVP_NOT_ATTENDING, 'declined' =>
                'Checked in by QR code. Invitee had declined RSVP; gate override allowed using invitation guest limit.',
            default =>
                'Checked in by QR code. RSVP was pending; invitation guest limit applied.',
        };
    }

    private function recordFailedAttempt(
        Invitee $invitee,
        string $message
    ): void {
        $gateLimit = $this->gateGuestLimit($invitee);

        $previousCount = max(
            0,
            (int) ($invitee->checked_in_count ?? 0)
        );

        $remainingGuests = max(
            0,
            $gateLimit - $previousCount
        );

        $invitee->checkIns()->create([
            'event_id' => $invitee->event_id,
            'checked_in_by' => Auth::id(),
            'checkin_method' => CheckIn::METHOD_QR,
            'guests_checked_in' => 0,
            'previous_checked_in_count' => $previousCount,
            'remaining_guests' => $remainingGuests,
            'status' => CheckIn::STATUS_FAILED,
            'remarks' => $message,
            'checked_in_at' => now(),
            'device_name' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);
    }

    private function recordDuplicateAttempt(
        Invitee $invitee,
        string $message
    ): void {
        $previousCount = max(
            0,
            (int) ($invitee->checked_in_count ?? 0)
        );

        $invitee->checkIns()->create([
            'event_id' => $invitee->event_id,
            'checked_in_by' => Auth::id(),
            'checkin_method' => CheckIn::METHOD_QR,
            'guests_checked_in' => 0,
            'previous_checked_in_count' => $previousCount,
            'remaining_guests' => 0,
            'status' => defined(CheckIn::class.'::STATUS_DUPLICATE')
                ? CheckIn::STATUS_DUPLICATE
                : 'duplicate',
            'remarks' => $message,
            'checked_in_at' => now(),
            'device_name' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);
    }
}
