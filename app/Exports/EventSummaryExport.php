<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventSummaryExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(protected int $eventId) {}

    public function collection(): Collection
    {
        $event = Event::query()->find($this->eventId);

        $inviteesQuery = Invitee::query()->where('event_id', $this->eventId);

        $totalInvitees = (clone $inviteesQuery)->count();
        $totalAllowedGuests = (clone $inviteesQuery)->sum('allowed_guests');
        $totalConfirmedGuests = Schema::hasColumn('invitees', 'confirmed_guests')
            ? (clone $inviteesQuery)->sum('confirmed_guests')
            : 0;

        $attending = (clone $inviteesQuery)->where('rsvp_status', Invitee::RSVP_ATTENDING)->count();
        $notAttending = (clone $inviteesQuery)->where('rsvp_status', Invitee::RSVP_NOT_ATTENDING)->count();
        $pendingRsvp = (clone $inviteesQuery)->where(function ($query): void {
            $query->whereNull('rsvp_status')
                ->orWhere('rsvp_status', '')
                ->orWhere('rsvp_status', Invitee::RSVP_PENDING);

            if (defined(Invitee::class . '::RSVP_MAYBE')) {
                $query->orWhere('rsvp_status', Invitee::RSVP_MAYBE);
            }
        })->count();

        $cardsGenerated = Schema::hasTable('generated_cards')
            ? GeneratedCard::query()
                ->where('event_id', $this->eventId)
                ->whereIn('status', [GeneratedCard::STATUS_GENERATED, GeneratedCard::STATUS_SENT])
                ->count()
            : 0;

        $cardsSent = Schema::hasTable('generated_cards')
            ? GeneratedCard::query()
                ->where('event_id', $this->eventId)
                ->where('status', GeneratedCard::STATUS_SENT)
                ->count()
            : 0;

        $smsSent = $this->messageLogCount('sms', ['sent', 'delivered']);
        $whatsappSent = $this->messageLogCount('whatsapp', ['sent', 'delivered', 'read']);
        $failedMessages = $this->failedMessageCount();

        $checkedInInvitees = (clone $inviteesQuery)->where('checked_in_count', '>', 0)->count();
        $totalGuestsCheckedIn = (clone $inviteesQuery)->sum('checked_in_count');
        $notCheckedIn = (clone $inviteesQuery)->where(function ($query): void {
            $query->whereNull('checked_in_count')
                ->orWhere('checked_in_count', 0);
        })->count();

        return collect([
            [
                'event_name' => (string) ($event?->title ?? $event?->name ?? 'Event #' . $this->eventId),
                'event_date' => $this->eventDate($event),
                'venue' => (string) ($event?->venue_name ?? $event?->venue ?? $event?->venue_address ?? '-'),
                'total_invitees' => $totalInvitees,
                'total_allowed_guests' => $totalAllowedGuests,
                'total_confirmed_guests' => $totalConfirmedGuests,
                'attending' => $attending,
                'not_attending' => $notAttending,
                'pending_rsvp' => $pendingRsvp,
                'cards_generated' => $cardsGenerated,
                'cards_sent' => $cardsSent,
                'sms_sent' => $smsSent,
                'whatsapp_sent' => $whatsappSent,
                'failed_messages' => $failedMessages,
                'checked_in_invitees' => $checkedInInvitees,
                'total_guests_checked_in' => $totalGuestsCheckedIn,
                'not_checked_in' => $notCheckedIn,
                'exported_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function map($row): array
    {
        return [
            $row['event_name'],
            $row['event_date'],
            $row['venue'],
            $row['total_invitees'],
            $row['total_allowed_guests'],
            $row['total_confirmed_guests'],
            $row['attending'],
            $row['not_attending'],
            $row['pending_rsvp'],
            $row['cards_generated'],
            $row['cards_sent'],
            $row['sms_sent'],
            $row['whatsapp_sent'],
            $row['failed_messages'],
            $row['checked_in_invitees'],
            $row['total_guests_checked_in'],
            $row['not_checked_in'],
            $row['exported_at'],
        ];
    }

    public function headings(): array
    {
        return [
            'Event Name',
            'Event Date',
            'Venue',
            'Total Invitees',
            'Total Allowed Guests',
            'Total Confirmed Guests',
            'Attending',
            'Not Attending',
            'Pending RSVP',
            'Cards Generated',
            'Cards Sent',
            'SMS Sent',
            'WhatsApp Sent',
            'Failed Messages',
            'Checked-in Invitees',
            'Total Guests Checked In',
            'Not Checked In',
            'Exported At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function messageLogCount(string $channel, array $statuses): int
    {
        if (! Schema::hasTable('message_logs')) {
            return 0;
        }

        return DB::table('message_logs')
            ->where('event_id', $this->eventId)
            ->where('channel', $channel)
            ->whereIn('status', $statuses)
            ->count();
    }

    protected function failedMessageCount(): int
    {
        if (! Schema::hasTable('message_logs')) {
            return 0;
        }

        return DB::table('message_logs')
            ->where('event_id', $this->eventId)
            ->whereIn('status', ['failed', 'rejected', 'undelivered'])
            ->count();
    }

    protected function eventDate(?Event $event): string
    {
        $date = $event?->event_date
            ?? $event?->date
            ?? $event?->starts_at
            ?? null;

        if (blank($date)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $date;
        }
    }
}
