<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CheckInDashboard extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static string $view =
        'filament.resources.event-resource.pages.check-in-dashboard';

    protected static ?string $title = 'Check-in Dashboard';

    public string $search = '';

    public ?int $selectedInviteeId = null;

    public int $guestsToCheckIn = 1;

    public bool $showInviteePanel = false;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasAnyRole([
            User::ROLE_SUPER_ADMIN,
            User::ROLE_EVENT_ADMIN,
            User::ROLE_CHECK_IN_OFFICER,
        ]) ?? false;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->authorizeEventAccess();
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('openScanner')
                ->label('Open QR Scanner')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->url(fn (): string => route('gate.check-in.entry', [
                    'event' => $this->record->getKey(),
                ]))
                ->openUrlInNewTab(),

            Actions\Action::make('backToEvents')
                ->label('Assigned Events')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl()),
        ];
    }

    public function updatedSearch(): void
    {
        $this->selectedInviteeId = null;
        $this->showInviteePanel = false;
    }

    public function selectInvitee(int $inviteeId): void
    {
        $this->authorizeEventAccess();

        $invitee = $this->inviteeQuery()
            ->whereKey($inviteeId)
            ->firstOrFail();

        $this->selectedInviteeId = $invitee->getKey();
        $this->guestsToCheckIn = 1;
        $this->showInviteePanel = true;
    }

    public function clearSelection(): void
    {
        $this->selectedInviteeId = null;
        $this->guestsToCheckIn = 1;
        $this->showInviteePanel = false;
    }

    public function manualCheckIn(): void
    {
        $this->authorizeEventAccess();

        $this->validate([
            'selectedInviteeId' => ['required', 'integer'],
            'guestsToCheckIn' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $result = DB::transaction(function (): array {
                $invitee = $this->inviteeQuery()
                    ->whereKey($this->selectedInviteeId)
                    ->lockForUpdate()
                    ->first();

                if (! $invitee) {
                    return [
                        'success' => false,
                        'message' => 'Invitee could not be found for this event.',
                    ];
                }

                $validationMessage = $this->validateInviteeForCheckIn($invitee);

                if ($validationMessage) {
                    $this->recordAttempt(
                        invitee: $invitee,
                        status: CheckIn::STATUS_FAILED,
                        guests: 0,
                        remarks: $validationMessage,
                    );

                    return [
                        'success' => false,
                        'message' => $validationMessage,
                    ];
                }

                $limit = $this->gateGuestLimit($invitee);
                $previous = max(0, (int) ($invitee->checked_in_count ?? 0));
                $remaining = max(0, $limit - $previous);

                if ($remaining <= 0) {
                    $message = 'Guest limit already reached for this invitation.';

                    $this->recordAttempt(
                        invitee: $invitee,
                        status: defined(CheckIn::class.'::STATUS_DUPLICATE')
                            ? CheckIn::STATUS_DUPLICATE
                            : 'duplicate',
                        guests: 0,
                        remarks: $message,
                    );

                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }

                if ($this->guestsToCheckIn > $remaining) {
                    $message = "Only {$remaining} guest(s) remaining.";

                    $this->recordAttempt(
                        invitee: $invitee,
                        status: CheckIn::STATUS_FAILED,
                        guests: 0,
                        remarks: $message,
                    );

                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }

                $newCount = $previous + $this->guestsToCheckIn;
                $remainingAfter = max(0, $limit - $newCount);

                $invitee->checkIns()->create([
                    'event_id' => $invitee->event_id,
                    'checked_in_by' => auth()->id(),
                    'checkin_method' => 'manual',
                    'guests_checked_in' => $this->guestsToCheckIn,
                    'previous_checked_in_count' => $previous,
                    'remaining_guests' => $remainingAfter,
                    'status' => $remainingAfter === 0
                        ? CheckIn::STATUS_SUCCESS
                        : 'partial',
                    'remarks' => 'Manual gate check-in from the officer workspace.',
                    'checked_in_at' => now(),
                    'device_name' => request()->userAgent(),
                    'ip_address' => request()->ip(),
                ]);

                $invitee->forceFill([
                    'checked_in_count' => $newCount,
                    'check_in_status' => $remainingAfter === 0
                        ? 'checked_in'
                        : 'partially_checked_in',
                    'checked_in_at' => now(),
                ])->save();

                return [
                    'success' => true,
                    'message' => "{$this->guestsToCheckIn} guest(s) checked in successfully.",
                ];
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Check-in failed')
                ->body('The check-in could not be completed. Please try again.')
                ->danger()
                ->send();

            return;
        }

        if (! $result['success']) {
            Notification::make()
                ->title('Check-in not completed')
                ->body($result['message'])
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Check-in successful')
            ->body($result['message'])
            ->success()
            ->send();

        $this->guestsToCheckIn = 1;
        $this->showInviteePanel = true;
    }

    public function getViewData(): array
    {
        $this->authorizeEventAccess();

        /** @var Event $event */
        $event = $this->record;

        $totalInvitees = $event->invitees()->count();
        $totalAllowedGuests = (int) $event->invitees()->sum('allowed_guests');
        $totalGuestsAdmitted = (int) $event->invitees()->sum('checked_in_count');
        $remainingGuests = max($totalAllowedGuests - $totalGuestsAdmitted, 0);

        $checkInRate = $totalAllowedGuests > 0
            ? round(min(100, ($totalGuestsAdmitted / $totalAllowedGuests) * 100), 1)
            : 0;

        $checkedInInvitees = $event->invitees()
            ->where('checked_in_count', '>', 0)
            ->count();

        $partialCheckIns = $event->invitees()
            ->where('checked_in_count', '>', 0)
            ->whereColumn('checked_in_count', '<', 'allowed_guests')
            ->count();

        $fullyCheckedIn = $event->invitees()
            ->where('allowed_guests', '>', 0)
            ->whereColumn('checked_in_count', '>=', 'allowed_guests')
            ->count();

        $failedAttempts = $event->checkIns()
            ->whereIn('status', ['failed', 'invalid', 'rejected'])
            ->count();

        $recentCheckIns = $event->checkIns()
            ->with(['invitee.cardType', 'checkedInBy'])
            ->latest('checked_in_at')
            ->latest('id')
            ->limit(12)
            ->get();

        $searchResults = collect();

        if (filled(trim($this->search))) {
            $term = trim($this->search);

            $searchResults = $this->inviteeQuery()
                ->where(function (Builder $query) use ($term): void {
                    $query
                        ->where('name', 'ilike', "%{$term}%")
                        ->orWhere('phone', 'ilike', "%{$term}%")
                        ->orWhere('serial_number', 'ilike', "%{$term}%");
                })
                ->orderBy('name')
                ->limit(15)
                ->get();
        }

        $selectedInvitee = $this->selectedInviteeId
            ? $this->inviteeQuery()->whereKey($this->selectedInviteeId)->first()
            : null;

        if ($selectedInvitee) {
            $selectedInvitee->setAttribute(
                'gate_limit',
                $this->gateGuestLimit($selectedInvitee)
            );

            $selectedInvitee->setAttribute(
                'remaining_guest_limit',
                max(
                    0,
                    $this->gateGuestLimit($selectedInvitee)
                    - (int) ($selectedInvitee->checked_in_count ?? 0)
                )
            );
        }

        return compact(
            'event',
            'totalInvitees',
            'totalAllowedGuests',
            'totalGuestsAdmitted',
            'remainingGuests',
            'checkInRate',
            'checkedInInvitees',
            'partialCheckIns',
            'fullyCheckedIn',
            'failedAttempts',
            'recentCheckIns',
            'searchResults',
            'selectedInvitee',
        );
    }

    private function authorizeEventAccess(): void
    {
        abort_unless(
            auth()->user()?->canCheckInForEvent($this->record) ?? false,
            403,
            'You are not assigned to manage check-in for this event.'
        );
    }

    private function inviteeQuery(): Builder
    {
        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $this->record->getKey());
    }

    private function validateInviteeForCheckIn(Invitee $invitee): ?string
    {
        $status = (string) ($invitee->card_status ?? 'active');

        if ($status === 'blocked') {
            return 'This invitation card is blocked.';
        }

        if ($status === 'cancelled') {
            return 'This invitation card is cancelled.';
        }

        if (! in_array($status, ['active', 'generated', 'sent'], true)) {
            return 'This invitation card is not valid for check-in.';
        }

        if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
            return 'This invitee responded that they will not attend. Contact the Event Manager.';
        }

        if ($this->gateGuestLimit($invitee) <= 0) {
            return 'No guests are allowed for this invitation.';
        }

        return null;
    }

    private function gateGuestLimit(Invitee $invitee): int
    {
        $allowed = max(
            1,
            (int) (
                $invitee->final_allowed_guests
                ?? $invitee->allowed_guests
                ?? $invitee->cardType?->allowed_guests
                ?? $invitee->cardType?->allowed_people
                ?? 1
            )
        );

        $confirmed = max(0, (int) ($invitee->confirmed_guests ?? 0));

        if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
            return 0;
        }

        if ($invitee->rsvp_status === 'attending' && $confirmed > 0) {
            return min($confirmed, $allowed);
        }

        return $allowed;
    }

    private function recordAttempt(
        Invitee $invitee,
        string $status,
        int $guests,
        string $remarks,
    ): void {
        $previous = max(0, (int) ($invitee->checked_in_count ?? 0));

        $invitee->checkIns()->create([
            'event_id' => $invitee->event_id,
            'checked_in_by' => auth()->id(),
            'checkin_method' => 'manual',
            'guests_checked_in' => $guests,
            'previous_checked_in_count' => $previous,
            'remaining_guests' => max(
                0,
                $this->gateGuestLimit($invitee) - $previous
            ),
            'status' => $status,
            'remarks' => $remarks,
            'checked_in_at' => now(),
            'device_name' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);
    }
}
