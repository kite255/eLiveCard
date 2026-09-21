<?php

namespace App\Filament\Resources\ContributionCampaignResource\Pages;

use App\Filament\Resources\ContributionCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContributionCampaign extends EditRecord
{
    protected static string $resource = ContributionCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
