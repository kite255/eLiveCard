<?php

namespace App\Filament\Resources\GeneratedCardResource\Pages;

use App\Filament\Resources\GeneratedCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeneratedCards extends ListRecords
{
    protected static string $resource = GeneratedCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
