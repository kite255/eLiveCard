<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\Invitee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventInviteesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected string $eventName = '-';

    public function __construct(
        protected int $eventId,
    ) {
        $event = Event::query()->find($this->eventId);

        $this->eventName = (string) ($event?->title ?? $event?->name ?? 'Event #' . $this->eventId);
    }

    public function collection(): Collection
    {
        return Invitee::query()
            ->with(['cardType'])
            ->where('event_id', $this->eventId)
            ->orderBy('name')
            ->get();
    }

    public function map($invitee): array
    {
        return [
            $this->eventName,
            $invitee->name ?: '-',
            $invitee->phone ?: '-',
            $invitee->cardType?->name ?? $invitee->card_type ?? '-',
            $invitee->allowed_guests ?? $invitee->guest_count ?? '-',
            $invitee->category ?: '-',
            $invitee->table_number ?: '-',
            $invitee->serial_number ?: '-',
            $this->formatStatus($invitee->rsvp_status ?? null),
            $this->formatStatus($invitee->card_status ?? null),
            $this->formatCheckInStatus($invitee),
            $invitee->checked_in_count ?? 0,
            $invitee->checked_in_at?->format('Y-m-d H:i:s') ?: '-',
            $invitee->created_at?->format('Y-m-d H:i:s') ?: '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Event Name',
            'Name',
            'Phone',
            'Card Type',
            'Allowed Guests',
            'Category',
            'Table Number',
            'Serial Number',
            'RSVP Status',
            'Card Status',
            'Check-in Status',
            'Checked-in Count',
            'Checked-in At',
            'Created At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function formatStatus(?string $status): string
    {
        if (blank($status)) {
            return '-';
        }

        return str((string) $status)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function formatCheckInStatus($invitee): string
    {
        if (filled($invitee->checked_in_at ?? null)) {
            return 'Checked In';
        }

        if ((int) ($invitee->checked_in_count ?? 0) > 0) {
            return 'Partially Checked In';
        }

        return 'Not Checked In';
    }
}
