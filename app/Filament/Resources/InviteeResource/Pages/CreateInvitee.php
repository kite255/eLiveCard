<?php

namespace App\Filament\Resources\InviteeResource\Pages;

use App\Filament\Resources\InviteeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvitee extends CreateRecord
{
    protected static string $resource = InviteeResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Invitee added successfully';
    }
}
