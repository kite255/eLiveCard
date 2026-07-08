<?php

namespace App\Filament\Resources\InviteeUploadResource\Pages;

use App\Filament\Resources\InviteeUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInviteeUpload extends EditRecord
{
    protected static string $resource = InviteeUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
