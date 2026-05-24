<?php

namespace App\Filament\Resources\CardTemplatePlaceholderResource\Pages;

use App\Filament\Resources\CardTemplatePlaceholderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCardTemplatePlaceholder extends EditRecord
{
    protected static string $resource = CardTemplatePlaceholderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
