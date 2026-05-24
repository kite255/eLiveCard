<?php

namespace App\Filament\Resources\CardTemplateResource\Pages;

use App\Filament\Resources\CardTemplateResource;
use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use App\Models\Invitee;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;

class CardTemplateDesigner extends Page
{
    protected static string $resource = CardTemplateResource::class;

    protected static string $view = 'filament.resources.card-template-resource.pages.card-template-designer';

    public CardTemplate $template;

    public array $placeholders = [];

    public ?string $selectedPlaceholder = CardTemplatePlaceholder::PLACEHOLDER_NAME;

    public int $zoom = 100;

    public bool $showPreview = false;

    public ?string $sampleQrCodeUrl = null;

    public function mount(int|string $record): void
    {
        $this->template = CardTemplate::query()
            ->with(['event', 'placeholders'])
            ->findOrFail($record);

        $this->loadPlaceholders();
        $this->loadSampleQrCode();
    }

    public function availablePlaceholders(): array
    {
        return CardTemplatePlaceholder::availablePlaceholders();
    }

    public function fontFamilyOptions(): array
    {
        return CardTemplatePlaceholder::fontFamilyOptions();
    }

    public function loadSampleQrCode(): void
    {
        $invitee = Invitee::query()
            ->where('event_id', $this->template->event_id)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('qr_code_path')
                    ->orWhereNotNull('qr_code');
            })
            ->latest('updated_at')
            ->first();

        if (! $invitee) {
            $this->sampleQrCodeUrl = null;

            return;
        }

        $path = $invitee->qr_code_path ?? $invitee->qr_code ?? null;

        if (blank($path)) {
            $this->sampleQrCodeUrl = null;

            return;
        }

        $this->sampleQrCodeUrl = $invitee->qr_code_url;
    }

    protected function defaultPlaceholders(): array
    {
        return [
            CardTemplatePlaceholder::PLACEHOLDER_NAME => [
                'label' => 'Invitee Name',
                'type' => 'text',
                'x_percent' => 25,
                'y_percent' => 38,
                'width_percent' => 50,
                'height_percent' => 6,
                'font_size' => 28,
                'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                'font_color' => '#3A2A1A',
                'font_weight' => 'bold',
                'text_align' => 'center',
                'is_visible' => true,
                'sort_order' => 1,
            ],

            CardTemplatePlaceholder::PLACEHOLDER_CARD_TYPE => [
                'label' => 'Card Type',
                'type' => 'text',
                'x_percent' => 35,
                'y_percent' => 25,
                'width_percent' => 30,
                'height_percent' => 5,
                'font_size' => 16,
                'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                'font_color' => '#3A2A1A',
                'font_weight' => 'bold',
                'text_align' => 'center',
                'is_visible' => true,
                'sort_order' => 2,
            ],

            CardTemplatePlaceholder::PLACEHOLDER_ALLOWED_GUESTS => [
                'label' => 'Allowed Guests',
                'type' => 'text',
                'x_percent' => 35,
                'y_percent' => 45,
                'width_percent' => 30,
                'height_percent' => 5,
                'font_size' => 14,
                'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                'font_color' => '#3A2A1A',
                'font_weight' => 'normal',
                'text_align' => 'center',
                'is_visible' => true,
                'sort_order' => 3,
            ],

            CardTemplatePlaceholder::PLACEHOLDER_QR_CODE => [
                'label' => 'QR Code',
                'type' => 'qr_code',
                'x_percent' => 10,
                'y_percent' => 72,
                'width_percent' => 22,
                'height_percent' => 12,
                'font_size' => 12,
                'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                'font_color' => '#111827',
                'font_weight' => 'normal',
                'text_align' => 'center',
                'qr_size' => 220,
                'qr_color' => '#111827',
                'qr_background_color' => '#FFFFFF',
                'is_visible' => true,
                'sort_order' => 4,
            ],

            CardTemplatePlaceholder::PLACEHOLDER_SERIAL_NUMBER => [
                'label' => 'Serial Number',
                'type' => 'text',
                'x_percent' => 8,
                'y_percent' => 87,
                'width_percent' => 28,
                'height_percent' => 5,
                'font_size' => 13,
                'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                'font_color' => '#3A2A1A',
                'font_weight' => 'bold',
                'text_align' => 'center',
                'is_visible' => true,
                'sort_order' => 5,
            ],

            CardTemplatePlaceholder::PLACEHOLDER_TABLE_NUMBER => [
                'label' => 'Table Number',
                'type' => 'text',
                'x_percent' => 62,
                'y_percent' => 87,
                'width_percent' => 28,
                'height_percent' => 5,
                'font_size' => 13,
                'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                'font_color' => '#3A2A1A',
                'font_weight' => 'bold',
                'text_align' => 'center',
                'is_visible' => true,
                'sort_order' => 6,
            ],
        ];
    }

    public function loadPlaceholders(): void
    {
        $saved = $this->template->placeholders
            ->sortBy('sort_order')
            ->keyBy('placeholder_key');

        $this->placeholders = [];

        if ($saved->isEmpty()) {
            foreach ($this->defaultPlaceholders() as $key => $default) {
                $this->placeholders[$key] = $this->buildPlaceholderArray($key, null, $default);
            }
        } else {
            foreach ($saved as $key => $placeholder) {
                $this->placeholders[$key] = $this->buildPlaceholderArray($key, $placeholder);
            }
        }

        if (! $this->selectedPlaceholder || ! array_key_exists($this->selectedPlaceholder, $this->placeholders)) {
            $this->selectedPlaceholder = array_key_first($this->placeholders);
        }
    }

    protected function buildPlaceholderArray(
        string $key,
        ?CardTemplatePlaceholder $placeholder = null,
        array $default = []
    ): array {
        $available = CardTemplatePlaceholder::availablePlaceholders();

        return [
            'key' => $key,

            'label' => $placeholder?->label
                ?: $default['label']
                ?: ($available[$key] ?? Str::headline($key)),

            'type' => $placeholder?->isQrCode()
                ? 'qr_code'
                : ($default['type'] ?? ($key === CardTemplatePlaceholder::PLACEHOLDER_QR_CODE ? 'qr_code' : 'text')),

            'x_percent' => (float) ($placeholder?->x_percent ?? $default['x_percent'] ?? 10),
            'y_percent' => (float) ($placeholder?->y_percent ?? $default['y_percent'] ?? 10),
            'width_percent' => (float) ($placeholder?->width_percent ?? $default['width_percent'] ?? 25),
            'height_percent' => (float) ($placeholder?->height_percent ?? $default['height_percent'] ?? 6),

            'font_size' => (int) ($placeholder?->font_size ?? $default['font_size'] ?? 16),
            'font_family' => $placeholder?->font_family
                ?? $default['font_family']
                ?? CardTemplatePlaceholder::defaultFontFamily(),
            'font_color' => $placeholder?->font_color ?? $default['font_color'] ?? '#000000',
            'font_weight' => $placeholder?->font_weight ?? $default['font_weight'] ?? 'normal',
            'text_align' => $placeholder?->text_align ?? $default['text_align'] ?? 'center',

            'qr_size' => (int) ($placeholder?->qr_size ?? $default['qr_size'] ?? 220),
            'qr_color' => $placeholder?->qr_color ?? $default['qr_color'] ?? '#111827',
            'qr_background_color' => $placeholder?->qr_background_color ?? $default['qr_background_color'] ?? '#FFFFFF',

            'is_visible' => (bool) ($placeholder?->is_visible ?? $default['is_visible'] ?? true),
            'sort_order' => (int) ($placeholder?->sort_order ?? $default['sort_order'] ?? 0),
        ];
    }

    public function addPlaceholder(string $key): void
    {
        $available = CardTemplatePlaceholder::availablePlaceholders();

        if (! array_key_exists($key, $available)) {
            Notification::make()
                ->title('Invalid placeholder')
                ->body('This placeholder is not supported.')
                ->danger()
                ->send();

            return;
        }

        if (array_key_exists($key, $this->placeholders)) {
            Notification::make()
                ->title('Placeholder already exists')
                ->body($available[$key] . ' is already on this template.')
                ->warning()
                ->send();

            $this->selectedPlaceholder = $key;

            return;
        }

        $type = $key === CardTemplatePlaceholder::PLACEHOLDER_QR_CODE ? 'qr_code' : 'text';

        $this->placeholders[$key] = [
            'key' => $key,
            'label' => $available[$key],
            'type' => $type,
            'x_percent' => 10,
            'y_percent' => 10,
            'width_percent' => $type === 'qr_code' ? 22 : 30,
            'height_percent' => $type === 'qr_code' ? 12 : 6,
            'font_size' => 16,
            'font_family' => CardTemplatePlaceholder::defaultFontFamily(),
            'font_color' => '#000000',
            'font_weight' => 'normal',
            'text_align' => 'center',
            'qr_size' => 220,
            'qr_color' => '#111827',
            'qr_background_color' => '#FFFFFF',
            'is_visible' => true,
            'sort_order' => count($this->placeholders) + 1,
        ];

        $this->selectedPlaceholder = $key;

        Notification::make()
            ->title('Placeholder added')
            ->body($available[$key] . ' has been added.')
            ->success()
            ->send();
    }

    public function removePlaceholder(string $key): void
    {
        if (! array_key_exists($key, $this->placeholders)) {
            return;
        }

        unset($this->placeholders[$key]);

        CardTemplatePlaceholder::query()
            ->where('card_template_id', $this->template->id)
            ->where('placeholder_key', $key)
            ->delete();

        $this->selectedPlaceholder = array_key_first($this->placeholders);

        Notification::make()
            ->title('Placeholder removed')
            ->body('Click Save Design to keep this change.')
            ->success()
            ->send();
    }

    public function selectPlaceholder(string $key): void
    {
        if (array_key_exists($key, $this->placeholders)) {
            $this->selectedPlaceholder = $key;
        }
    }

    public function hidePlaceholder(string $key): void
    {
        if (! array_key_exists($key, $this->placeholders)) {
            return;
        }

        $this->placeholders[$key]['is_visible'] = false;

        Notification::make()
            ->title('Placeholder hidden')
            ->body('Click Save Design to keep this change.')
            ->warning()
            ->send();
    }

    public function showPlaceholder(string $key): void
    {
        if (! array_key_exists($key, $this->placeholders)) {
            return;
        }

        $this->placeholders[$key]['is_visible'] = true;

        Notification::make()
            ->title('Placeholder shown')
            ->body('Click Save Design to keep this change.')
            ->success()
            ->send();
    }

    public function savePositions(): void
    {
        $keys = array_keys($this->placeholders);

        CardTemplatePlaceholder::query()
            ->where('card_template_id', $this->template->id)
            ->when(
                count($keys) > 0,
                fn ($query) => $query->whereNotIn('placeholder_key', $keys)
            )
            ->delete();

        foreach ($this->placeholders as $key => $placeholder) {
            $fontFamily = $placeholder['font_family'] ?? CardTemplatePlaceholder::defaultFontFamily();

            if (! array_key_exists($fontFamily, CardTemplatePlaceholder::fontFamilyOptions())) {
                $fontFamily = CardTemplatePlaceholder::defaultFontFamily();
            }

            CardTemplatePlaceholder::query()->updateOrCreate(
                [
                    'card_template_id' => $this->template->id,
                    'placeholder_key' => $key,
                ],
                [
                    'label' => $placeholder['label'] ?? Str::headline($key),

                    'x_percent' => $this->clampPercent($placeholder['x_percent'] ?? 0),
                    'y_percent' => $this->clampPercent($placeholder['y_percent'] ?? 0),
                    'width_percent' => $this->clampPercent($placeholder['width_percent'] ?? 20, 1, 100),
                    'height_percent' => $this->clampPercent($placeholder['height_percent'] ?? 6, 1, 100),

                    'font_size' => max(8, min(120, (int) ($placeholder['font_size'] ?? 16))),
                    'font_family' => $fontFamily,
                    'font_color' => $placeholder['font_color'] ?? '#000000',
                    'font_weight' => $placeholder['font_weight'] ?? 'normal',
                    'text_align' => $placeholder['text_align'] ?? 'center',

                    'qr_size' => max(60, min(800, (int) ($placeholder['qr_size'] ?? 220))),
                    'qr_color' => $placeholder['qr_color'] ?? '#111827',
                    'qr_background_color' => $placeholder['qr_background_color'] ?? '#FFFFFF',

                    'is_visible' => (bool) ($placeholder['is_visible'] ?? true),
                    'sort_order' => (int) ($placeholder['sort_order'] ?? 0),
                ]
            );
        }

        Notification::make()
            ->title('Design saved')
            ->body('Placeholder positions, styles, and fonts have been saved successfully.')
            ->success()
            ->send();

        $this->template->refresh();
        $this->template->load(['event', 'placeholders']);

        $this->loadPlaceholders();
        $this->loadSampleQrCode();
    }

    protected function clampPercent(mixed $value, float $min = 0, float $max = 100): float
    {
        $value = (float) $value;

        return round(max($min, min($max, $value)), 4);
    }

    public function resetPositions(): void
    {
        $this->template->placeholders()->delete();

        $this->selectedPlaceholder = CardTemplatePlaceholder::PLACEHOLDER_NAME;
        $this->zoom = 100;
        $this->showPreview = false;

        $this->template->load(['event', 'placeholders']);

        $this->loadPlaceholders();
        $this->loadSampleQrCode();

        Notification::make()
            ->title('Designer reset')
            ->body('Placeholder positions have been reset to default.')
            ->warning()
            ->send();
    }

    public function previewCard(): void
    {
        $this->showPreview = true;

        Notification::make()
            ->title('Preview opened')
            ->body('Preview uses sample invitee data and an actual generated QR code.')
            ->info()
            ->send();
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

    public function getSelectedPlaceholderProperty(): ?array
    {
        if (! $this->selectedPlaceholder) {
            return null;
        }

        return $this->placeholders[$this->selectedPlaceholder] ?? null;
    }
}