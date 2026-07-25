<?php

namespace App\Services;

use App\Jobs\SendWelcomeSmsJob;
use App\Models\CheckIn;
use App\Models\Invitee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckInService
{
    public function checkIn(
        Invitee $invitee,
        int $guestsCount,
        ?User $user = null,
        string $method = CheckIn::METHOD_QR,
        ?int $expectedEventId = null
    ): array {
        $guestsCount = max(1, $guestsCount);

        try {
            return DB::transaction(function () use (
                $invitee,
                $guestsCount,
                $user,
                $method,
                $expectedEventId
            ): array {
                $lockedInvitee = Invitee::query()
                    ->whereKey($invitee->id)
                    ->with(['event', 'cardType'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $expectedEventId !== null
                    && (int) $lockedInvitee->event_id !== $expectedEventId
                ) {
                    return [
                        'success' => false,
                        'title' => 'Wrong Event',
                        'message' => 'This invitation belongs to another event.',
                    ];
                }

                $method = $this->normalizeMethod($method);

                $allowedGuests = $this->allowedGuests($lockedInvitee);
                $confirmedGuests = $this->confirmedGuests($lockedInvitee);
                $gateLimit = $this->gateGuestLimit(
                    invitee: $lockedInvitee,
                    allowedGuests: $allowedGuests,
                    confirmedGuests: $confirmedGuests,
                );

                $previousCheckedInCount = max(
                    0,
                    (int) ($lockedInvitee->checked_in_count ?? 0)
                );

                $remainingBeforeCheckIn = max(
                    0,
                    $gateLimit - $previousCheckedInCount
                );

                $cardStatus = $lockedInvitee->card_status ?? 'active';

                if (! in_array($cardStatus, ['active', 'generated', 'sent'], true)) {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: $remainingBeforeCheckIn,
                        status: CheckIn::STATUS_BLOCKED,
                        remarks: 'Card is not active.',
                    );

                    return [
                        'success' => false,
                        'title' => 'Blocked Card',
                        'message' => 'This invitation card is not active.',
                    ];
                }

                if ($gateLimit <= 0) {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: 0,
                        status: CheckIn::STATUS_BLOCKED,
                        remarks: 'No guests are allowed for this invitation.',
                    );

                    return [
                        'success' => false,
                        'title' => 'Check-in Not Allowed',
                        'message' => 'No guests are allowed for this invitation.',
                    ];
                }

                if ($remainingBeforeCheckIn <= 0) {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: 0,
                        status: CheckIn::STATUS_DUPLICATE,
                        remarks: 'Guest limit already reached.',
                    );

                    return [
                        'success' => false,
                        'title' => 'Guest Limit Reached',
                        'message' => 'This card has already used all allowed entries.',
                    ];
                }

                if ($guestsCount > $remainingBeforeCheckIn) {
                    $this->recordAttempt(
                        invitee: $lockedInvitee,
                        user: $user,
                        method: $method,
                        guestsCheckedIn: 0,
                        previousCheckedInCount: $previousCheckedInCount,
                        remainingGuests: $remainingBeforeCheckIn,
                        status: CheckIn::STATUS_FAILED,
                        remarks: "Only {$remainingBeforeCheckIn} guest(s) remaining.",
                    );

                    return [
                        'success' => false,
                        'title' => 'Too Many Guests',
                        'message' => "Only {$remainingBeforeCheckIn} guest(s) remaining for this card.",
                    ];
                }

                $newCheckedInCount = $previousCheckedInCount + $guestsCount;
                $remainingAfterCheckIn = max(
                    0,
                    $gateLimit - $newCheckedInCount
                );

                $oldValues = $lockedInvitee->only([
                    'checked_in_count',
                    'check_in_status',
                    'checked_in_at',
                ]);

                $lockedInvitee->update([
                    'checked_in_count' => $newCheckedInCount,
                    'check_in_status' => $remainingAfterCheckIn <= 0
                        ? 'checked_in'
                        : 'partially_checked_in',
                    'checked_in_at' => now(),
                ]);

                $checkIn = $this->recordAttempt(
                    invitee: $lockedInvitee,
                    user: $user,
                    method: $method,
                    guestsCheckedIn: $guestsCount,
                    previousCheckedInCount: $previousCheckedInCount,
                    remainingGuests: $remainingAfterCheckIn,
                    status: CheckIn::STATUS_SUCCESS,
                    remarks: $this->successRemarks(
                        invitee: $lockedInvitee,
                        guestsCount: $guestsCount,
                    ),
                );

                AuditLogService::updated(
                    subject: $lockedInvitee,
                    eventId: $lockedInvitee->event_id,
                    description: 'Invitee check-in details were updated.',
                    oldValues: $oldValues,
                    newValues: $lockedInvitee->only([
                        'checked_in_count',
                        'check_in_status',
                        'checked_in_at',
                    ]),
                    metadata: [
                        'check_in_id' => $checkIn->id,
                        'method' => $method,
                        'guests_checked_in' => $guestsCount,
                        'allowed_guests' => $allowedGuests,
                        'confirmed_guests' => $confirmedGuests,
                        'rsvp_status' => $lockedInvitee->rsvp_status ?? 'pending',
                        'gate_limit' => $gateLimit,
                        'previous_checked_in_count' => $previousCheckedInCount,
                        'remaining_guests' => $remainingAfterCheckIn,
                        'gate_user_id' => $user?->id,
                    ],
                    userId: $user?->id,
                );

                $this->dispatchWelcomeSmsAfterCommit($lockedInvitee);

                return [
                    'success' => true,
                    'title' => 'Check-in Successful',
                    'message' => "{$guestsCount} guest(s) checked in successfully.",
                    'check_in_id' => $checkIn->id,
                    'event_id' => $lockedInvitee->event_id,
                    'invitee_id' => $lockedInvitee->id,
                    'allowed_guests' => $allowedGuests,
                    'confirmed_guests' => $confirmedGuests,
                    'gate_limit' => $gateLimit,
                    'checked_in_count' => $newCheckedInCount,
                    'remaining_guests' => $remainingAfterCheckIn,
                    'rsvp_status' => $lockedInvitee->rsvp_status ?? 'pending',
                ];
            });
        } catch (Throwable $e) {
            report($e);

            AuditLogService::record(
                action: 'invitee.check_in_exception',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'Check-in failed because of an unexpected system error.',
                metadata: [
                    'method' => $method,
                    'requested_guests' => $guestsCount,
                    'gate_user_id' => $user?->id,
                    'error' => $e->getMessage(),
                ],
                userId: $user?->id,
            );

            return [
                'success' => false,
                'title' => 'Check-in Failed',
                'message' => 'Something went wrong while checking in this guest.',
            ];
        }
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

    private function confirmedGuests(Invitee $invitee): int
    {
        return max(0, (int) ($invitee->confirmed_guests ?? 0));
    }

    private function gateGuestLimit(
        Invitee $invitee,
        int $allowedGuests,
        int $confirmedGuests
    ): int {
        return $allowedGuests;
    }

    private function successRemarks(
        Invitee $invitee,
        int $guestsCount
    ): string {
        $rsvpStatus = (string) ($invitee->rsvp_status ?? 'pending');

        $rsvpNote = match ($rsvpStatus) {
            'attending', 'yes', 'confirmed' => 'RSVP confirmed.',
            'not_attending', 'declined' => 'RSVP was declined; gate override allowed.',
            default => 'RSVP was pending or not confirmed.',
        };

        return "{$guestsCount} guest(s) checked in successfully. {$rsvpNote} Invitation guest limit applied.";
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        $allowedMethods = [
            'qr',
            'manual',
            'serial',
            'phone',
            'name',
            'gate_scanner',
        ];

        return in_array($method, $allowedMethods, true)
            ? $method
            : CheckIn::METHOD_QR;
    }

    private function dispatchWelcomeSmsAfterCommit(Invitee $invitee): void
    {
        $event = $invitee->event;

        if (! $event || ! (bool) ($event->welcome_sms_enabled ?? false)) {
            return;
        }

        DB::afterCommit(function () use ($invitee): void {
            try {
                SendWelcomeSmsJob::dispatch($invitee->id);

                AuditLogService::record(
                    action: 'invitee.welcome_sms_queued',
                    subject: $invitee,
                    eventId: $invitee->event_id,
                    description: 'Welcome SMS was queued after successful check-in.',
                    metadata: [
                        'invitee_id' => $invitee->id,
                        'phone' => $invitee->phone,
                    ],
                );
            } catch (Throwable $exception) {
                Log::warning('Failed to dispatch welcome SMS job after check-in', [
                    'invitee_id' => $invitee->id,
                    'event_id' => $invitee->event_id,
                    'error' => $exception->getMessage(),
                ]);

                AuditLogService::record(
                    action: 'invitee.welcome_sms_queue_failed',
                    subject: $invitee,
                    eventId: $invitee->event_id,
                    description: 'Welcome SMS could not be queued after check-in.',
                    metadata: [
                        'invitee_id' => $invitee->id,
                        'error' => $exception->getMessage(),
                    ],
                );
            }
        });
    }

    private function recordAttempt(
        Invitee $invitee,
        ?User $user,
        string $method,
        int $guestsCheckedIn,
        int $previousCheckedInCount,
        int $remainingGuests,
        string $status,
        ?string $remarks = null
    ): CheckIn {
        $payload = [
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'checked_in_by' => $user?->id,
            'status' => $status,
            'remarks' => $remarks,
            'checked_in_at' => now(),
        ];

        $this->addFirstAvailableColumn(
            payload: $payload,
            columns: ['method', 'check_in_method', 'checkin_method'],
            value: $method,
        );

        $this->addFirstAvailableColumn(
            payload: $payload,
            columns: [
                'guest_count',
                'guests_count',
                'guests_checked_in',
                'checked_in_count',
                'quantity',
            ],
            value: $guestsCheckedIn,
        );

        $this->addFirstAvailableColumn(
            payload: $payload,
            columns: ['previous_checked_in_count'],
            value: $previousCheckedInCount,
        );

        $this->addFirstAvailableColumn(
            payload: $payload,
            columns: ['remaining_guests'],
            value: $remainingGuests,
        );

        $checkIn = CheckIn::query()->create($payload);

        AuditLogService::record(
            action: $this->auditActionForStatus($status),
            subject: $checkIn,
            eventId: $invitee->event_id,
            description: $this->auditDescriptionForStatus(
                status: $status,
                invitee: $invitee,
                remarks: $remarks,
            ),
            metadata: [
                'invitee_id' => $invitee->id,
                'invitee_name' => $invitee->name,
                'serial_number' => $invitee->serial_number,
                'method' => $method,
                'status' => $status,
                'guests_checked_in' => $guestsCheckedIn,
                'previous_checked_in_count' => $previousCheckedInCount,
                'remaining_guests' => $remainingGuests,
                'gate_user_id' => $user?->id,
                'remarks' => $remarks,
            ],
            userId: $user?->id,
        );

        return $checkIn;
    }

    private function addFirstAvailableColumn(
        array &$payload,
        array $columns,
        mixed $value
    ): void {
        foreach ($columns as $column) {
            if (Schema::hasColumn('check_ins', $column)) {
                $payload[$column] = $value;

                return;
            }
        }
    }

    private function auditActionForStatus(string $status): string
    {
        return match ($status) {
            CheckIn::STATUS_SUCCESS => 'check_in.success',
            CheckIn::STATUS_BLOCKED => 'check_in.blocked',
            CheckIn::STATUS_DUPLICATE => 'check_in.duplicate',
            CheckIn::STATUS_FAILED => 'check_in.failed',
            default => 'check_in.attempted',
        };
    }

    private function auditDescriptionForStatus(
        string $status,
        Invitee $invitee,
        ?string $remarks = null,
    ): string {
        $name = $invitee->name ?: 'Invitee #'.$invitee->id;

        return match ($status) {
            CheckIn::STATUS_SUCCESS => "{$name} was checked in successfully.",
            CheckIn::STATUS_BLOCKED => "{$name} check-in was blocked.",
            CheckIn::STATUS_DUPLICATE => "{$name} check-in was rejected because the guest limit was already reached.",
            CheckIn::STATUS_FAILED => "{$name} check-in failed validation.",
            default => "{$name} check-in was attempted.",
        }.($remarks ? " {$remarks}" : '');
    }
}
