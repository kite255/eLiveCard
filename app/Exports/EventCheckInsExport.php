<?php

namespace App\Exports;

use App\Models\CheckIn;
use App\Models\Event;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class EventCheckInsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected ?Event $event = null;

    protected bool $auditRecorded = false;

    protected string $exportedAt;

    public function __construct(
        protected int $eventId,
    ) {
        $this->event = Event::query()->find($this->eventId);
        $this->exportedAt = now()->format('Y-m-d H:i:s');
    }

    public function collection(): Collection
    {
        if (! $this->event) {
            throw new RuntimeException(
                "Event with ID {$this->eventId} was not found."
            );
        }

        if (! Schema::hasTable('check_ins')) {
            $records = collect();

            $this->recordExportAudit($records);

            return $records;
        }

        $records = CheckIn::query()
            ->with([
                'invitee.cardType',
                'checkedInBy',
            ])
            ->where('event_id', $this->eventId)
            ->latest(
                $this->checkInsColumnExists('checked_in_at')
                    ? 'checked_in_at'
                    : 'created_at'
            )
            ->get();

        $this->recordExportAudit($records);

        return $records;
    }

    public function map($checkIn): array
    {
        $invitee = $checkIn->invitee;
        $event = $this->event ?? $checkIn->event ?? null;

        $allowedGuests = (int) (
            $invitee?->allowed_guests
            ?? $invitee?->cardType?->allowed_people
            ?? 1
        );

        $guestsCheckedIn = (int) (
            $checkIn->guests_checked_in
            ?? $checkIn->checked_in_count
            ?? $invitee?->checked_in_count
            ?? 0
        );

        $remainingGuests = $checkIn->remaining_guests;

        if ($remainingGuests === null) {
            $remainingGuests = max(
                0,
                $allowedGuests - $guestsCheckedIn
            );
        }

        $checkInMethod = $checkIn->checkin_method
            ?? $checkIn->method
            ?? '-';

        $gateUser = $checkIn->checkedInBy?->name
            ?? $checkIn->user?->name
            ?? $checkIn->checked_in_by
            ?? '-';

        $checkedInAt = $checkIn->checked_in_at
            ?? $checkIn->created_at
            ?? null;

        return [
            $event?->title
                ?? $event?->name
                ?? '-',

            $invitee?->name
                ?? '-',

            $invitee?->phone
                ?? $checkIn->phone
                ?? '-',

            $invitee?->serial_number
                ?? $checkIn->serial_number
                ?? '-',

            $invitee?->cardType?->name
                ?? '-',

            $allowedGuests,

            $guestsCheckedIn,

            (int) $remainingGuests,

            str((string) $checkInMethod)
                ->replace('_', ' ')
                ->title()
                ->toString(),

            str((string) ($checkIn->status ?? '-'))
                ->replace('_', ' ')
                ->title()
                ->toString(),

            $gateUser,

            $checkedInAt?->format('Y-m-d H:i:s')
                ?? '-',

            $checkIn->remarks
                ?? $checkIn->note
                ?? '-',

            $this->exportedAt,
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
            'Exported At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }

    protected function checkInsColumnExists(string $column): bool
    {
        return Schema::hasTable('check_ins')
            && Schema::hasColumn('check_ins', $column);
    }

    protected function recordExportAudit(Collection $records): void
    {
        if ($this->auditRecorded) {
            return;
        }

        $this->auditRecorded = true;

        $successfulStatuses = [
            'success',
            'checked_in',
            'valid',
            'partial',
        ];

        $failedStatuses = [
            'failed',
            'invalid',
            'rejected',
        ];

        $successfulRecords = $records
            ->filter(
                fn ($record): bool =>
                    in_array(
                        (string) ($record->status ?? ''),
                        $successfulStatuses,
                        true
                    )
            )
            ->count();

        $failedRecords = $records
            ->filter(
                fn ($record): bool =>
                    in_array(
                        (string) ($record->status ?? ''),
                        $failedStatuses,
                        true
                    )
            )
            ->count();

        $totalGuestsCheckedIn = $records
            ->sum(
                fn ($record): int => (int) (
                    $record->guests_checked_in
                    ?? $record->checked_in_count
                    ?? 0
                )
            );

        $methods = $records
            ->map(
                fn ($record): string => (string) (
                    $record->checkin_method
                    ?? $record->method
                    ?? 'unknown'
                )
            )
            ->countBy()
            ->all();

        AuditLogService::exported(
            subject: $this->event,
            eventId: $this->eventId,
            description: 'Event check-in records were exported.',
            metadata: [
                'export_type' => 'event_check_ins',
                'row_count' => $records->count(),
                'successful_records' => $successfulRecords,
                'failed_records' => $failedRecords,
                'total_guests_checked_in' => $totalGuestsCheckedIn,
                'methods' => $methods,
                'event_name' => $this->event?->title
                    ?? $this->event?->name
                    ?? 'Event #'.$this->eventId,
                'exported_at' => $this->exportedAt,
            ],
        );
    }
}
