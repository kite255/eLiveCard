<?php

namespace App\Filament\Resources\SmsLogResource\Pages;

use App\Filament\Resources\SmsLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSmsLog extends ViewRecord
{
    protected static string $resource = SmsLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
