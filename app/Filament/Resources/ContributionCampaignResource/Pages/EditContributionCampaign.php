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
        return [
            Actions\Action::make('designer')
                ->label('Open Designer')
                ->icon('heroicon-o-cursor-arrow-rays')
                ->color('primary')
                ->url(ContributionCampaignResource::getUrl('designer', [
                    'record' => $this->record,
                ])),
            Actions\DeleteAction::make(),
        ];
    }
}
