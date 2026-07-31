<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Str;

class ManageEventSection extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static string $view =
        'filament.resources.event-resource.pages.manage-event-section';

    protected static ?string $title = 'Event Section';

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    public function getSection(): string
    {
        $section = (string) request()->route('section');

        abort_unless(
            array_key_exists($section, EventResource::relationManagerMap()),
            404,
            'Unknown event workspace section.',
        );

        return $section;
    }

    public function getSectionLabel(): string
    {
        return match ($this->getSection()) {
            EventResource::RELATION_ASSIGNED_USERS => 'Assigned Users',
            EventResource::RELATION_CARD_TYPES => 'Card Types',
            EventResource::RELATION_INVITEES => 'Invitees',
            EventResource::RELATION_INVITEE_UPLOADS => 'Wishes & Photos',
            EventResource::RELATION_CARD_TEMPLATES => 'Card Templates',
            EventResource::RELATION_GENERATED_CARDS => 'Generated Cards',
            EventResource::RELATION_MESSAGE_TEMPLATES => 'Message Templates',
            EventResource::RELATION_MESSAGE_LOGS => 'Message Logs',
            EventResource::RELATION_SMS_LOGS => 'SMS Logs',
            EventResource::RELATION_CHECK_INS => 'Check-ins',
            EventResource::RELATION_AUDIT_LOGS => 'Activity Log',
            default => Str::headline($this->getSection()),
        };
    }

    public function getTitle(): string
    {
        return $this->getSectionLabel();
    }

    public function getHeading(): string
    {
        return $this->getSectionLabel();
    }

    public function getSubheading(): ?string
    {
        return 'Manage '.$this->getSectionLabel().' for '.$this->getEventName().'.';
    }

    public function getEventName(): string
    {
        return (string) (
            $this->record->title
            ?? $this->record->name
            ?? 'Untitled Event'
        );
    }

    public function getWorkspaceUrl(): string
    {
        return EventResource::getUrl('view', [
            'record' => $this->record,
        ]);
    }
}
