<?php

namespace App\Filament\Resources\SmsLogResource\Pages;

use App\Filament\Resources\SmsLogResource;
use App\Filament\Widgets\SmsLogStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSmsLogs extends ListRecords
{
    protected static string $resource = SmsLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            SmsLogStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}