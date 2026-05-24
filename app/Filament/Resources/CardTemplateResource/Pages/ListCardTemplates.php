<?php

namespace App\Filament\Resources\CardTemplateResource\Pages;

use App\Filament\Resources\CardTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCardTemplates extends ListRecords
{
    protected static string $resource = CardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
