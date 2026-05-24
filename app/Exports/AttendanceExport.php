<?php

namespace App\Exports;

use App\Models\Invitee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        protected ?int $eventId = null,
        protected ?array $inviteeIds = null,
    ) {}

    public function collection(): Collection
    {
        return Invitee::query()
            ->with([
                'event',
                'cardType',
                'checkIns' => fn ($query) => $query->latest('checked_in_at'),
                'checkIns.checkedInBy',
            ])
            ->when($this->eventId, function (Builder $query): void {
                $query->where('event_id', $this->eventId);
            })
            ->when($this->inviteeIds, function (Builder $query): void {
                $query->whereIn('id', $this->inviteeIds);
            })
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Event',
            'Invitee Name',
            'Phone',
            'Card Type',
            'Serial Number',
            'Allowed Guests',
            'Confirmed Guests',
            'Checked-in Guests',
            'Remaining Guests',
            'Attendance Status',
            'RSVP Status',
            'Last Check-in At',
            'Last Checked-in By',
            'Last Check-in Method',
            'Total Check-in Records',
            'Card Status',
        ];
    }

    public function map($invitee): array
    {
        $lastCheckIn = $invitee->checkIns->first();

        return [
            $invitee->event?->title ?? $invitee->event?->name ?? '',
            $invitee->name,
            $invitee->phone,
            $invitee->cardType?->name ?? '',
            $invitee->serial_number,
            $invitee->final_allowed_guests,
            $invitee->confirmed_guests,
            $invitee->checked_in_count,
            $invitee->remaining_guests,
            $invitee->checked_in_count > 0 ? 'Attended' : 'Not Attended',
            Invitee::rsvpStatuses()[$invitee->rsvp_status] ?? ucfirst((string) $invitee->rsvp_status),
            optional($lastCheckIn?->checked_in_at)->format('Y-m-d H:i:s'),
            $lastCheckIn?->checkedInBy?->name ?? '',
            $lastCheckIn?->checkin_method ?? '',
            $invitee->checkIns->count(),
            $invitee->card_status,
        ];
    }
}
