<?php

namespace App\Filament\Resources\InviteeResource\Pages;

use App\Filament\Resources\InviteeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvitee extends EditRecord
{
    protected static string $resource = InviteeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
