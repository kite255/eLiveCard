<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Services\AuditLogService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Event values captured before saving, used to build the audit comparison.
     */
    protected array $auditOldValues = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Open Event Workspace')
                ->icon('heroicon-o-folder-open'),

            Actions\DeleteAction::make()
                ->label('Delete Event')
                ->before(function (): void {
                    AuditLogService::deleted(
                        subject: $this->record,
                        eventId: $this->record->id,
                        description: 'Event was deleted.',
                        metadata: [
                            'event_title' => $this->record->title,
                            'event_type' => $this->record->event_type,
                            'event_date' => $this->record->event_date,
                            'status' => $this->record->status,
                            'owner_user_id' => $this->record->user_id,
                        ],
                    );
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $this->auditOldValues = $this->record->only($this->auditableFields());
    }

    protected function afterSave(): void
    {
        $this->record->refresh();

        $newValues = $this->record->only($this->auditableFields());

        $changedOldValues = [];
        $changedNewValues = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $this->auditOldValues[$field] ?? null;

            if ($this->valuesAreEqual($oldValue, $newValue)) {
                continue;
            }

            $changedOldValues[$field] = $oldValue;
            $changedNewValues[$field] = $newValue;
        }

        if ($changedNewValues === []) {
            return;
        }

        AuditLogService::updated(
            subject: $this->record,
            eventId: $this->record->id,
            description: $this->buildAuditDescription($changedNewValues),
            oldValues: $changedOldValues,
            newValues: $changedNewValues,
            metadata: [
                'source' => 'filament_admin',
                'changed_fields' => array_keys($changedNewValues),
                'changed_fields_count' => count($changedNewValues),
            ],
        );
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Event updated successfully';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'Edit Event: '.$this->record->title;
    }

    /**
     * Fields that are safe and useful to retain in the event activity history.
     */
    protected function auditableFields(): array
    {
        return [
            'user_id',
            'title',
            'event_type',
            'event_date',
            'start_time',
            'end_time',
            'venue_name',
            'venue_address',
            'google_maps_link',
            'dress_code',
            'program',
            'contact_person_name',
            'contact_person_phone',
            'organizer_phone',
            'status',
            'welcome_message',
            'love_story',
            'cover_image',

            'show_cover_image',
            'show_love_story',
            'show_program',
            'show_countdown',
            'show_wishes',
            'show_organizer_contact',
            'show_photo_upload',

            'auto_sms_reminders_enabled',
            'auto_rsvp_pending_reminder_enabled',
            'auto_one_day_reminder_enabled',
            'auto_event_day_reminder_enabled',
            'welcome_sms_enabled',
            'welcome_sms_message',
            'rsvp_pending_reminder_time',
            'one_day_reminder_time',
            'event_day_reminder_time',
        ];
    }

    protected function valuesAreEqual(mixed $oldValue, mixed $newValue): bool
    {
        if ($oldValue instanceof \BackedEnum) {
            $oldValue = $oldValue->value;
        }

        if ($newValue instanceof \BackedEnum) {
            $newValue = $newValue->value;
        }

        if ($oldValue instanceof \DateTimeInterface) {
            $oldValue = $oldValue->format('Y-m-d H:i:s');
        }

        if ($newValue instanceof \DateTimeInterface) {
            $newValue = $newValue->format('Y-m-d H:i:s');
        }

        if (is_bool($oldValue) || is_bool($newValue)) {
            return (bool) $oldValue === (bool) $newValue;
        }

        return (string) ($oldValue ?? '') === (string) ($newValue ?? '');
    }

    protected function buildAuditDescription(array $changedValues): string
    {
        $changedFields = array_keys($changedValues);

        if (in_array('user_id', $changedFields, true)) {
            return 'Event details and ownership were updated.';
        }

        if (in_array('status', $changedFields, true)) {
            return 'Event details and status were updated.';
        }

        $reminderFields = [
            'auto_sms_reminders_enabled',
            'auto_rsvp_pending_reminder_enabled',
            'auto_one_day_reminder_enabled',
            'auto_event_day_reminder_enabled',
            'welcome_sms_enabled',
            'welcome_sms_message',
            'rsvp_pending_reminder_time',
            'one_day_reminder_time',
            'event_day_reminder_time',
        ];

        if (array_intersect($changedFields, $reminderFields) !== []) {
            return 'Event reminder and messaging settings were updated.';
        }

        $publicPageFields = [
            'welcome_message',
            'love_story',
            'cover_image',
            'show_cover_image',
            'show_love_story',
            'show_program',
            'show_countdown',
            'show_wishes',
            'show_organizer_contact',
            'show_photo_upload',
        ];

        if (array_intersect($changedFields, $publicPageFields) !== []) {
            return 'Invitee page content and visibility settings were updated.';
        }

        return 'Event details were updated.';
    }
}
