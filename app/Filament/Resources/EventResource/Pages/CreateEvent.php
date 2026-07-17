<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function afterCreate(): void
    {
        $event = $this->record;

        AuditLogService::created(
            subject: $event,
            eventId: $event->id,
            description: 'Event was created.',
            metadata: [
                'source' => 'filament_admin',
                'event_type' => $event->event_type,
                'status' => $event->status,
                'event_date' => $event->event_date,
                'venue_name' => $event->venue_name,
                'owner_user_id' => $event->user_id,
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
