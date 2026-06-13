<?php

namespace App\Filament\Resources\MessageLogResource\Pages;

use App\Filament\Resources\MessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessageLog extends EditRecord
{
    protected static string $resource = MessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
