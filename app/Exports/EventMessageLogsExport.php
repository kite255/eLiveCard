<?php

namespace App\Exports;

use App\Models\MessageLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventMessageLogsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected int $eventId,
        protected bool $failedOnly = false,
    ) {}

    public function collection(): Collection
    {
        return MessageLog::query()
            ->with('invitee')
            ->where('event_id', $this->eventId)
            ->when(
                $this->failedOnly,
                fn ($query) => $query->whereIn('status', ['failed', 'rejected'])
            )
            ->latest('created_at')
            ->get();
    }

    public function map($log): array
    {
        return [
            $log->invitee?->name ?? '-',
            $log->phone ?: '-',
            strtoupper((string) ($log->channel ?: '-')),
            str((string) ($log->type ?: '-'))->replace('_', ' ')->title()->toString(),
            str((string) ($log->status ?: 'unknown'))->replace('_', ' ')->title()->toString(),
            $log->provider ?: '-',
            $log->provider_message_id ?: '-',
            $log->error_message ?: '-',
            $log->sent_at?->format('Y-m-d H:i:s') ?: '-',
            $log->delivered_at?->format('Y-m-d H:i:s') ?: '-',
            $log->failed_at?->format('Y-m-d H:i:s') ?: '-',
            $log->created_at?->format('Y-m-d H:i:s') ?: '-',
            $log->message ?: '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Invitee Name',
            'Phone',
            'Channel',
            'Type',
            'Status',
            'Provider',
            'Provider Message ID',
            'Error Message',
            'Sent At',
            'Delivered At',
            'Failed At',
            'Created At',
            'Message',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
