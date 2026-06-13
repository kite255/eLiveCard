<?php

namespace App\Filament\Resources\MessageLogResource\Pages;

use App\Filament\Resources\MessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMessageLog extends CreateRecord
{
    protected static string $resource = MessageLogResource::class;
}
