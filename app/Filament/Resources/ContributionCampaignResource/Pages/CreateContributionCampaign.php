<?php

namespace App\Filament\Resources\ContributionCampaignResource\Pages;

use App\Filament\Resources\ContributionCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContributionCampaign extends CreateRecord
{
    protected static string $resource = ContributionCampaignResource::class;

    protected function getRedirectUrl(): string
    {
        return ContributionCampaignResource::getUrl('designer', [
            'record' => $this->record,
        ]);
    }
}
