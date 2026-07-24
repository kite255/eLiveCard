<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\User;
use App\Services\AuditLogService;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Enforce event ownership before the record is created.
     *
     * Super Admin:
     * - May create an event for any eligible active owner selected in the form.
     *
     * Event Manager:
     * - May create an event only for themselves.
     *
     * Check-in Officer:
     * - May not create events.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        abort_unless(
            $user?->hasAnyRole([
                User::ROLE_SUPER_ADMIN,
                User::ROLE_EVENT_ADMIN,
            ]),
            403
        );

        if ($user->isEventAdmin()) {
            $data['user_id'] = $user->getKey();
        }

        return $data;
    }

    /**
     * Record the event creation in the audit log.
     */
    protected function afterCreate(): void
    {
        $event = $this->record;

        AuditLogService::created(
            subject: $event,
            eventId: $event->getKey(),
            description: 'Event was created.',
            metadata: [
                'source' => 'filament_admin',
                'created_by_user_id' => auth()->id(),
                'owner_user_id' => $event->user_id,
                'event_type' => $event->event_type,
                'status' => $event->status,
                'event_date' => $event->event_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i:s'),
                'end_time' => $event->end_time?->format('H:i:s'),
                'venue_name' => $event->venue_name,
            ],
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Event created successfully';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }
}
