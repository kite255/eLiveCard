<?php

namespace App\Filament\Resources\ContributionCampaignResource\Pages;

use App\Filament\Resources\ContributionCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContributionCampaign extends CreateRecord
{
    protected static string $resource = ContributionCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ContributionCampaignResource::getUrl('designer', [
            'record' => $this->record,
        ]);
    }
}
