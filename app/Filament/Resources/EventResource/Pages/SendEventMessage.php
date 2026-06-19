<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Resources\Pages\Page;

class SendEventMessage extends Page
{
    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.send-event-message';

    public function getTitle(): string
    {
        return 'Send Message';
    }
}