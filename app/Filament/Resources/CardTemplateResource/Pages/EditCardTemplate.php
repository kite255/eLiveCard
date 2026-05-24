<?php

namespace App\Filament\Resources\CardTemplateResource\Pages;

use App\Filament\Resources\CardTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCardTemplate extends EditRecord
{
    protected static string $resource = CardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
