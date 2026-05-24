<?php

namespace App\Filament\Resources\CardTemplatePlaceholderResource\Pages;

use App\Filament\Resources\CardTemplatePlaceholderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCardTemplatePlaceholders extends ListRecords
{
    protected static string $resource = CardTemplatePlaceholderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
