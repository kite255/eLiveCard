<?php

namespace App\Exports;

use App\Models\CheckIn;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventCheckInsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected ?Event $event = null;

    public function __construct(
        protected int $eventId,
    ) {
        $this->event = Event::find($this->eventId);
    }

    public function collection(): Collection
    {
        return CheckIn::query()
            ->with([
                'invitee.cardType',
                'user',
            ])
            ->where('event_id', $this->eventId)
            ->latest($this->checkInsColumnExists('checked_in_at') ? 'checked_in_at' : 'created_at')
            ->get();
    }

    public function map($checkIn): array
    {
        $invitee = $checkIn->invitee;
        $event = $this->event ?? $checkIn->event ?? null;

        $allowedGuests = (int) ($invitee?->allowed_guests ?? 1);
        $guestsCheckedIn = (int) (
            $checkIn->guests_checked_in
            ?? $checkIn->checked_in_count
            ?? $invitee?->checked_in_count
            ?? 0
        );

        $remainingGuests = $checkIn->remaining_guests;

        if ($remainingGuests === null) {
            $remainingGuests = max(0, $allowedGuests - $guestsCheckedIn);
        }

        return [
            $event?->title ?? $event?->name ?? '-',
            $invitee?->name ?? '-',
            $invitee?->phone ?? $checkIn->phone ?? '-',
            $invitee?->serial_number ?? $checkIn->serial_number ?? '-',
            $invitee?->cardType?->name ?? '-',
            $allowedGuests,
            $guestsCheckedIn,
            $remainingGuests,
            str((string) ($checkIn->checkin_method ?? $checkIn->method ?? '-'))->replace('_', ' ')->title()->toString(),
            str((string) ($checkIn->status ?? '-'))->replace('_', ' ')->title()->toString(),
            $checkIn->user?->name ?? $checkIn->checkedInBy?->name ?? $checkIn->checked_in_by ?? '-',
            $checkIn->checked_in_at?->format('Y-m-d H:i:s')
                ?? $checkIn->created_at?->format('Y-m-d H:i:s')
                ?? '-',
            $checkIn->remarks ?? $checkIn->note ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Event Name',
            'Invitee Name',
            'Phone',
            'Serial Number',
            'Card Type',
            'Allowed Guests',
            'Guests Checked In',
            'Remaining Guests',
            'Check-in Method',
            'Status',
            'Gate User',
            'Checked In At',
            'Remarks',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function checkInsColumnExists(string $column): bool
    {
        return Schema::hasTable('check_ins') && Schema::hasColumn('check_ins', $column);
    }
}
