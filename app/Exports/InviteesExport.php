<?php

namespace App\Exports;

use App\Models\Invitee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InviteesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        protected ?int $eventId = null,
        protected ?array $inviteeIds = null,
    ) {}

    public function collection(): Collection
    {
        return Invitee::query()
            ->with(['event', 'cardType'])
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
            'Name',
            'Phone',
            'Email',
            'Event',
            'Card Type',
            'Category',
            'Table Number',
            'Allowed Guests',
            'Confirmed Guests',
            'RSVP Status',
            'RSVP Confirmed At',
            'Serial Number',
            'Short Code',
            'Invitation Link',
            'RSVP Link',
            'SMS Status',
            'Invitation SMS Status',
            'Invitation SMS Sent At',
            'Reminder SMS Status',
            'Reminder SMS Sent At',
            'Final SMS Status',
            'Final SMS Sent At',
            'SMS Message ID',
            'SMS Error',
            'Checked-in Count',
            'Remaining Guests',
            'Checked-in At',
            'Card Status',
        ];
    }

    public function map($invitee): array
    {
        return [
            $invitee->name,
            $invitee->phone,
            $invitee->email,
            $invitee->event?->title ?? $invitee->event?->name ?? '',
            $invitee->cardType?->name ?? '',
            $invitee->category,
            $invitee->table_number,
            $invitee->final_allowed_guests,
            $invitee->confirmed_guests,
            Invitee::rsvpStatuses()[$invitee->rsvp_status] ?? ucfirst((string) $invitee->rsvp_status),
            optional($invitee->rsvp_confirmed_at)->format('Y-m-d H:i:s'),
            $invitee->serial_number,
            $invitee->short_code,
            $invitee->private_invitation_url,
            $invitee->rsvp_url,
            $invitee->sms_status,
            $invitee->invitation_sms_status,
            optional($invitee->invitation_sms_sent_at)->format('Y-m-d H:i:s'),
            $invitee->reminder_sms_status,
            optional($invitee->reminder_sms_sent_at)->format('Y-m-d H:i:s'),
            $invitee->final_sms_status,
            optional($invitee->final_sms_sent_at)->format('Y-m-d H:i:s'),
            $invitee->sms_message_id,
            $invitee->sms_error ?? $invitee->last_sms_error,
            $invitee->checked_in_count,
            $invitee->remaining_guests,
            optional($invitee->checked_in_at)->format('Y-m-d H:i:s'),
            $invitee->card_status,
        ];
    }
}
