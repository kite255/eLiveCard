<?php

namespace App\Filament\Resources\CheckInResource\Pages;

use App\Filament\Resources\CheckInResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCheckIn extends ViewRecord
{
    protected static string $resource = CheckInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->hidden(),
        ];
    }
}