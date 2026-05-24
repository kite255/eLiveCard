<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Invitee;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class EventRsvpReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.event-rsvp-report';

    protected static ?string $navigationLabel = 'RSVP Report';

    protected static ?string $title = 'Event RSVP Report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public ?int $eventId = null;

    public function mount(): void
    {
        $this->eventId = Event::query()
            ->latest('id')
            ->value('id');
    }

    public function getEventsProperty(): Collection
    {
        return Event::query()
            ->orderByDesc('id')
            ->get(['id', 'title', 'event_date']);
    }

    public function getSelectedEventProperty(): ?Event
    {
        if (! $this->eventId) {
            return null;
        }

        return Event::query()->find($this->eventId);
    }

    public function getInviteesProperty(): Collection
    {
        if (! $this->eventId) {
            return collect();
        }

        return Invitee::query()
            ->with('cardType')
            ->where('event_id', $this->eventId)
            ->get();
    }

    public function getReportProperty(): array
    {
        $invitees = $this->invitees;

        $totalInvitees = $invitees->count();

        $totalAllowedGuests = $invitees->sum(
            fn (Invitee $invitee): int => (int) $invitee->final_allowed_guests
        );

        $rsvpPending = $invitees
            ->where('rsvp_status', Invitee::RSVP_PENDING)
            ->count();

        $attending = $invitees
            ->where('rsvp_status', Invitee::RSVP_ATTENDING)
            ->count();

        $notAttending = $invitees
            ->where('rsvp_status', Invitee::RSVP_NOT_ATTENDING)
            ->count();

        $maybe = $invitees
            ->where('rsvp_status', Invitee::RSVP_MAYBE)
            ->count();

        $confirmedGuests = $invitees
            ->where('rsvp_status', Invitee::RSVP_ATTENDING)
            ->sum(fn (Invitee $invitee): int => (int) ($invitee->confirmed_guests ?: $invitee->final_allowed_guests));

        $checkedInGuests = $invitees->sum(
            fn (Invitee $invitee): int => (int) $invitee->checked_in_count
        );

        $remainingGuests = $invitees->sum(
            fn (Invitee $invitee): int => (int) $invitee->remaining_guests
        );

        $invitationSmsSent = $invitees
            ->filter(fn (Invitee $invitee): bool => in_array($invitee->invitation_sms_status, [
                Invitee::SMS_STATUS_SENT,
                Invitee::SMS_STATUS_DELIVERED,
            ], true))
            ->count();

        $reminderSmsSent = $invitees
            ->filter(fn (Invitee $invitee): bool => in_array($invitee->reminder_sms_status, [
                Invitee::SMS_STATUS_SENT,
                Invitee::SMS_STATUS_DELIVERED,
            ], true))
            ->count();

        $finalSmsSent = $invitees
            ->filter(fn (Invitee $invitee): bool => in_array($invitee->final_sms_status, [
                Invitee::SMS_STATUS_SENT,
                Invitee::SMS_STATUS_DELIVERED,
            ], true))
            ->count();

        $smsFailed = $invitees
            ->filter(fn (Invitee $invitee): bool => $invitee->sms_status === Invitee::SMS_STATUS_FAILED
                || $invitee->invitation_sms_status === Invitee::SMS_STATUS_FAILED
                || $invitee->reminder_sms_status === Invitee::SMS_STATUS_FAILED
                || $invitee->final_sms_status === Invitee::SMS_STATUS_FAILED)
            ->count();

        return [
            'total_invitees' => $totalInvitees,
            'total_allowed_guests' => $totalAllowedGuests,
            'rsvp_pending' => $rsvpPending,
            'attending' => $attending,
            'not_attending' => $notAttending,
            'maybe' => $maybe,
            'confirmed_guests' => $confirmedGuests,
            'checked_in_guests' => $checkedInGuests,
            'remaining_guests' => $remainingGuests,
            'invitation_sms_sent' => $invitationSmsSent,
            'reminder_sms_sent' => $reminderSmsSent,
            'final_sms_sent' => $finalSmsSent,
            'sms_failed' => $smsFailed,
            'rsvp_response_rate' => $totalInvitees > 0
                ? round((($attending + $notAttending + $maybe) / $totalInvitees) * 100, 1)
                : 0,
            'attendance_rate' => $totalAllowedGuests > 0
                ? round(($checkedInGuests / $totalAllowedGuests) * 100, 1)
                : 0,
        ];
    }

    public function getCardTypeSummaryProperty(): Collection
    {
        return $this->invitees
            ->groupBy(fn (Invitee $invitee): string => $invitee->cardType?->name ?? 'No Card Type')
            ->map(function (Collection $invitees, string $cardType): array {
                return [
                    'card_type' => $cardType,
                    'invitees' => $invitees->count(),
                    'allowed_guests' => $invitees->sum(fn (Invitee $invitee): int => (int) $invitee->final_allowed_guests),
                    'attending' => $invitees->where('rsvp_status', Invitee::RSVP_ATTENDING)->count(),
                    'confirmed_guests' => $invitees
                        ->where('rsvp_status', Invitee::RSVP_ATTENDING)
                        ->sum(fn (Invitee $invitee): int => (int) ($invitee->confirmed_guests ?: $invitee->final_allowed_guests)),
                    'checked_in' => $invitees->sum(fn (Invitee $invitee): int => (int) $invitee->checked_in_count),
                ];
            })
            ->values();
    }

    public function getRsvpSummaryProperty(): Collection
    {
        return collect(Invitee::rsvpStatuses())
            ->map(function (string $label, string $status): array {
                $invitees = $this->invitees->where('rsvp_status', $status);

                return [
                    'status' => $label,
                    'count' => $invitees->count(),
                    'confirmed_guests' => $invitees->sum(fn (Invitee $invitee): int => (int) $invitee->confirmed_guests),
                ];
            })
            ->values();
    }
}