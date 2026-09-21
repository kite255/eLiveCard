<?php

namespace App\Filament\Resources\ContributionCampaignResource\Pages;

use App\Filament\Resources\ContributionCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContributionCampaigns extends ListRecords
{
    protected static string $resource = ContributionCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New Contribution Campaign')];
    }
}
