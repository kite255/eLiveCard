<?php

namespace App\Filament\Resources\InviteeUploadResource\Pages;

use App\Filament\Resources\InviteeUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInviteeUploads extends ListRecords
{
    protected static string $resource = InviteeUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
