<?php

namespace App\Filament\Resources\ContributionCampaignResource\Pages;

use App\Filament\Resources\ContributionCampaignResource;
use App\Models\CardTemplatePlaceholder;
use App\Models\ContributionCampaign;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\Rule;

class ContributionCampaignDesigner extends Page
{
    protected static string $resource = ContributionCampaignResource::class;

    protected static string $view = 'filament.resources.contribution-campaign-resource.pages.contribution-campaign-designer';

    public ContributionCampaign $campaign;

    public float $nameXPercent = 10;

    public float $nameYPercent = 70;

    public float $nameWidthPercent = 80;

    public int $nameFontSize = 42;

    public string $nameFontColor = '#111827';

    public string $nameFontFamily = CardTemplatePlaceholder::FONT_MONTSERRAT;

    public string $nameFontWeight = 'bold';

    public string $nameTextAlign = 'center';

    public string $previewName = 'Committee Member Name';

    public function mount(int|string $record): void
    {
        $this->campaign = ContributionCampaign::query()
            ->with('event')
            ->findOrFail($record);

        abort_unless(
            ContributionCampaignResource::canEdit($this->campaign),
            403
        );

        $this->loadDesign();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Campaign Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(ContributionCampaignResource::getUrl('edit', [
                    'record' => $this->campaign,
                ])),
        ];
    }

    public function getTitle(): string
    {
        return 'Contribution Card Designer';
    }

    public function fontFamilyOptions(): array
    {
        return CardTemplatePlaceholder::fontFamilyOptions();
    }

    public function saveDesign(): void
    {
        $data = $this->validate([
            'nameXPercent' => ['required', 'numeric', 'min:0', 'max:99'],
            'nameYPercent' => ['required', 'numeric', 'min:0', 'max:99'],
            'nameWidthPercent' => ['required', 'numeric', 'min:1', 'max:100'],
            'nameFontSize' => ['required', 'integer', 'min:8', 'max:300'],
            'nameFontColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'nameFontFamily' => [
                'required',
                Rule::in(array_keys(CardTemplatePlaceholder::fontFamilyOptions())),
            ],
            'nameFontWeight' => ['required', Rule::in(['normal', 'bold'])],
            'nameTextAlign' => ['required', Rule::in(['left', 'center', 'right'])],
            'previewName' => ['required', 'string', 'max:255'],
        ]);

        $width = min(100, max(1, (float) $data['nameWidthPercent']));
        $x = min(100 - $width, max(0, (float) $data['nameXPercent']));

        $this->campaign->forceFill([
            'name_x_percent' => round($x, 4),
            'name_y_percent' => round(
                min(99, max(0, (float) $data['nameYPercent'])),
                4
            ),
            'name_width_percent' => round($width, 4),
            'name_font_size' => (int) $data['nameFontSize'],
            'name_font_color' => strtoupper($data['nameFontColor']),
            'name_font_family' => $data['nameFontFamily'],
            'name_font_weight' => $data['nameFontWeight'],
            'name_text_align' => $data['nameTextAlign'],
        ])->save();

        $this->campaign->refresh();
        $this->loadDesign();

        Notification::make()
            ->title('Contribution card design saved')
            ->body('Newly generated cards will use this placeholder position and style.')
            ->success()
            ->send();
    }

    public function resetDesign(): void
    {
        $this->nameXPercent = 10;
        $this->nameYPercent = 70;
        $this->nameWidthPercent = 80;
        $this->nameFontSize = 42;
        $this->nameFontColor = '#111827';
        $this->nameFontFamily = CardTemplatePlaceholder::FONT_MONTSERRAT;
        $this->nameFontWeight = 'bold';
        $this->nameTextAlign = 'center';

        $this->saveDesign();
    }

    private function loadDesign(): void
    {
        $this->nameXPercent = (float) $this->campaign->name_x_percent;
        $this->nameYPercent = (float) $this->campaign->name_y_percent;
        $this->nameWidthPercent = (float) $this->campaign->name_width_percent;
        $this->nameFontSize = (int) $this->campaign->name_font_size;
        $this->nameFontColor = (string) $this->campaign->name_font_color;
        $this->nameFontFamily = (string) $this->campaign->name_font_family;
        $this->nameFontWeight = (string) $this->campaign->name_font_weight;
        $this->nameTextAlign = (string) $this->campaign->name_text_align;
    }
}
