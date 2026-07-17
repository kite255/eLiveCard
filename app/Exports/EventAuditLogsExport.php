<?php

namespace App\Exports;

use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class EventAuditLogsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected ?Event $event;

    public function __construct(
        protected int $eventId,
    ) {
        $this->event = Event::query()->find($this->eventId);
    }

    public function collection(): Collection
    {
        if (! $this->event) {
            throw new RuntimeException(
                "Event with ID {$this->eventId} was not found."
            );
        }

        return AuditLog::query()
            ->with('user')
            ->where('event_id', $this->eventId)
            ->latest('created_at')
            ->get();
    }

    public function map($log): array
    {
        return [
            $log->created_at?->format('Y-m-d H:i:s') ?? '-',
            $log->user?->name ?? 'System',
            $this->formatAction($log->action),
            $log->description ?? '-',
            $log->subject_label
                ?? class_basename((string) $log->subject_type)
                ?? '-',
            $log->subject_id ?? '-',
            $log->ip_address ?? '-',
            $this->encodeValue($log->old_values),
            $this->encodeValue($log->new_values),
            $this->encodeValue($log->metadata),
            $log->user_agent ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Date and Time',
            'Performed By',
            'Action',
            'Description',
            'Record Type',
            'Record ID',
            'IP Address',
            'Previous Values',
            'New Values',
            'Metadata',
            'Browser / Device',
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

    protected function formatAction(?string $action): string
    {
        return str($action ?? 'activity')
            ->replace(['.', '_', '-'], ' ')
            ->headline()
            ->toString();
    }

    protected function encodeValue(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        if (is_string($value)) {
            return $value;
        }

        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );
    }
}
