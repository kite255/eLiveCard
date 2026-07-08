<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\Invitee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class EventRsvpExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected int $eventId,
    ) {}

    public function collection(): Collection
    {
        return Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $this->eventId)
            ->orderBy('name')
            ->get();
    }

    public function map($invitee): array
    {
        /** @var Invitee $invitee */
        $event = $invitee->event ?: Event::find($this->eventId);

        return [
            $event?->title ?? $event?->name ?? 'Event #' . $this->eventId,
            $invitee->name ?: '-',
            $invitee->phone ?: '-',
            $invitee->cardType?->name ?? '-',
            $invitee->allowed_guests ?? 1,
            $invitee->confirmed_guests ?? 0,
            $this->formatStatus($invitee->rsvp_status),
            $this->formatDateTime($invitee->rsvp_confirmed_at ?? null),
            $invitee->table_number ?: '-',
            $invitee->category ?: '-',
            $invitee->serial_number ?: '-',
            filled($invitee->short_code) ? route('invitee.page', $invitee->short_code) : '-',
            $this->formatDateTime($invitee->created_at ?? null),
        ];
    }

    public function headings(): array
    {
        return [
            'Event Name',
            'Invitee Name',
            'Phone',
            'Card Type',
            'Allowed Guests',
            'Confirmed Guests',
            'RSVP Status',
            'RSVP Confirmed At',
            'Table Number',
            'Category',
            'Serial Number',
            'Invitation Link',
            'Created At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function formatStatus(?string $status): string
    {
        return str((string) ($status ?: 'pending'))
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    protected function formatDateTime($value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
