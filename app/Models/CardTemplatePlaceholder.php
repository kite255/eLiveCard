<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardTemplatePlaceholder extends Model
{
    public const PLACEHOLDER_NAME = 'name';
    public const PLACEHOLDER_CARD_TYPE = 'card_type';
    public const PLACEHOLDER_QR_CODE = 'qr_code';
    public const PLACEHOLDER_SERIAL_NUMBER = 'serial_number';
    public const PLACEHOLDER_GUEST_COUNT = 'guest_count';
    public const PLACEHOLDER_ALLOWED_GUESTS = 'allowed_guests';
    public const PLACEHOLDER_TABLE_NUMBER = 'table_number';
    public const PLACEHOLDER_CATEGORY = 'category';
    public const PLACEHOLDER_EVENT_NAME = 'event_name';
    public const PLACEHOLDER_EVENT_DATE = 'event_date';
    public const PLACEHOLDER_EVENT_TIME = 'event_time';
    public const PLACEHOLDER_EVENT_VENUE = 'event_venue';

    public const FONT_MONTSERRAT = 'Montserrat';
    public const FONT_ROBOTO = 'Roboto';
    public const FONT_LEXEND = 'Lexend';
    public const FONT_CORBEN = 'Corben';

    public const DEFAULT_FONT_SIZE = 32;
    public const DEFAULT_FONT_COLOR = '#111827';

    /*
    |--------------------------------------------------------------------------
    | Universal QR defaults
    |--------------------------------------------------------------------------
    | These values are applied automatically to every new QR placeholder before
    | the designer changes its size, position, foreground, or background.
    |
    | QR output quality starts at 221px and cannot be reduced below 221px.
    */
    public const DEFAULT_QR_SIZE = 221;
    public const MIN_QR_SIZE = 221;
    public const MAX_QR_SIZE = 1200;
    public const DEFAULT_QR_WIDTH_PERCENT = 16;
    public const DEFAULT_QR_HEIGHT_PERCENT = 9;
    public const DEFAULT_QR_COLOR = '#000000';
    public const DEFAULT_QR_BACKGROUND_COLOR = '#FFFFFF';
    public const MIN_SAFE_QR_CONTRAST = 4.5;

    protected $fillable = [
        'card_template_id',
        'placeholder_key',
        'label',
        'x_percent',
        'y_percent',
        'width_percent',
        'height_percent',
        'font_size',
        'font_color',
        'font_weight',
        'font_family',
        'text_align',
        'qr_size',
        'qr_color',
        'qr_background_color',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'card_template_id' => 'integer',
        'x_percent' => 'decimal:4',
        'y_percent' => 'decimal:4',
        'width_percent' => 'decimal:4',
        'height_percent' => 'decimal:4',
        'font_size' => 'integer',
        'qr_size' => 'integer',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CardTemplatePlaceholder $placeholder): void {
            $placeholder->applyCreationDefaults();
        });

        static::saving(function (CardTemplatePlaceholder $placeholder): void {
            $placeholder->normalizeValues();
        });
    }

    protected function applyCreationDefaults(): void
    {
        if (blank($this->label) && filled($this->placeholder_key)) {
            $this->label = self::availablePlaceholders()[$this->placeholder_key]
                ?? ucfirst(str_replace('_', ' ', (string) $this->placeholder_key));
        }

        $this->font_size ??= self::DEFAULT_FONT_SIZE;
        $this->font_color ??= self::DEFAULT_FONT_COLOR;
        $this->font_weight ??= 'normal';
        $this->font_family ??= self::defaultFontFamily();
        $this->text_align ??= 'center';

        $this->qr_size ??= self::DEFAULT_QR_SIZE;
        $this->qr_color ??= self::DEFAULT_QR_COLOR;
        $this->qr_background_color ??= self::DEFAULT_QR_BACKGROUND_COLOR;

        $this->is_visible ??= true;
        $this->sort_order ??= 1;

        /*
        |--------------------------------------------------------------------------
        | Default placement
        |--------------------------------------------------------------------------
        | Text placeholders begin at the center reference point.
        | QR placeholders begin at 16% width and calculate their height from the template aspect ratio.
        */
        $this->x_percent ??= $this->isQrCode()
            ? (100 - self::DEFAULT_QR_WIDTH_PERCENT) / 2
            : 50;

        $this->y_percent ??= $this->isQrCode() ? 58 : 50;

        $this->width_percent ??= $this->isQrCode()
            ? self::DEFAULT_QR_WIDTH_PERCENT
            : 60;

        $this->height_percent ??= $this->isQrCode()
            ? $this->resolveDefaultQrHeightPercent()
            : 8;
    }

    protected function normalizeValues(): void
    {
        $this->font_color = self::normalizeHexColor(
            $this->font_color,
            self::DEFAULT_FONT_COLOR
        );

        $this->qr_color = self::normalizeHexColor(
            $this->qr_color,
            self::DEFAULT_QR_COLOR
        );

        $this->qr_background_color = self::normalizeHexColor(
            $this->qr_background_color,
            self::DEFAULT_QR_BACKGROUND_COLOR
        );

        $this->font_size = max(
            8,
            min(
                300,
                (int) ($this->font_size ?: self::DEFAULT_FONT_SIZE)
            )
        );

        $this->qr_size = max(
            self::MIN_QR_SIZE,
            min(
                self::MAX_QR_SIZE,
                (int) ($this->qr_size ?: self::DEFAULT_QR_SIZE)
            )
        );

        $this->width_percent = self::clampPercent(
            $this->width_percent,
            $this->isQrCode() ? self::DEFAULT_QR_WIDTH_PERCENT : 60,
            minimum: 1
        );

        $this->height_percent = self::clampPercent(
            $this->height_percent,
            $this->isQrCode()
                ? $this->resolveDefaultQrHeightPercent()
                : 8,
            minimum: 1
        );

        $this->x_percent = self::clampPercent(
            $this->x_percent,
            $this->isQrCode()
                ? (100 - self::DEFAULT_QR_WIDTH_PERCENT) / 2
                : 50,
            maximum: max(0, 100 - (float) $this->width_percent)
        );

        $this->y_percent = self::clampPercent(
            $this->y_percent,
            $this->isQrCode() ? 58 : 50,
            maximum: max(0, 100 - (float) $this->height_percent)
        );

        $this->font_weight = in_array(
            $this->font_weight,
            array_keys(self::fontWeightOptions()),
            true
        ) ? $this->font_weight : 'normal';

        $this->text_align = in_array(
            $this->text_align,
            array_keys(self::textAlignOptions()),
            true
        ) ? $this->text_align : 'center';

        if (! array_key_exists(
            (string) $this->font_family,
            self::fontFamilyOptions()
        )) {
            $this->font_family = self::defaultFontFamily();
        }
    }

    /**
     * Calculate the QR height percentage required to keep the QR square in
     * actual pixels for the current card-template dimensions.
     *
     * Example:
     * 16% width on a 1931 × 2728 template becomes 11.3255% height.
     */
    public function resolveDefaultQrHeightPercent(): float
    {
        if (! $this->isQrCode()) {
            return self::DEFAULT_QR_HEIGHT_PERCENT;
        }

        $template = $this->relationLoaded('cardTemplate')
            ? $this->getRelation('cardTemplate')
            : null;

        if (! $template && filled($this->card_template_id)) {
            $template = CardTemplate::query()
                ->select(['id', 'width', 'height'])
                ->find($this->card_template_id);
        }

        $templateWidth = (float) ($template?->width ?? 0);
        $templateHeight = (float) ($template?->height ?? 0);

        if ($templateWidth <= 0 || $templateHeight <= 0) {
            return self::DEFAULT_QR_HEIGHT_PERCENT;
        }

        return round(
            (self::DEFAULT_QR_WIDTH_PERCENT * $templateWidth)
                / $templateHeight,
            4
        );
    }

    public static function availablePlaceholders(): array
    {
        return [
            self::PLACEHOLDER_NAME => 'Invitee Name',
            self::PLACEHOLDER_CARD_TYPE => 'Card Type',
            self::PLACEHOLDER_QR_CODE => 'QR Code',
            self::PLACEHOLDER_SERIAL_NUMBER => 'Serial Number',
            self::PLACEHOLDER_GUEST_COUNT => 'Guest Count',
            self::PLACEHOLDER_ALLOWED_GUESTS => 'Allowed Guests',
            self::PLACEHOLDER_TABLE_NUMBER => 'Table Number',
            self::PLACEHOLDER_CATEGORY => 'Category',
            self::PLACEHOLDER_EVENT_NAME => 'Event Name',
            self::PLACEHOLDER_EVENT_DATE => 'Event Date',
            self::PLACEHOLDER_EVENT_TIME => 'Event Time',
            self::PLACEHOLDER_EVENT_VENUE => 'Venue',
        ];
    }

    public static function textAlignOptions(): array
    {
        return [
            'left' => 'Left',
            'center' => 'Center',
            'right' => 'Right',
        ];
    }

    public static function fontWeightOptions(): array
    {
        return [
            'normal' => 'Normal',
            'bold' => 'Bold',
        ];
    }

    public static function fontFamilyOptions(): array
    {
        return [
            self::FONT_MONTSERRAT => 'Montserrat',
            self::FONT_ROBOTO => 'Roboto',
            self::FONT_LEXEND => 'Lexend',
            self::FONT_CORBEN => 'Corben',
        ];
    }

    public static function fontFiles(): array
    {
        return [
            self::FONT_MONTSERRAT => [
                'regular' => resource_path('fonts/Montserrat-Regular.ttf'),
                'bold' => resource_path('fonts/Montserrat-Bold.ttf'),
            ],
            self::FONT_ROBOTO => [
                'regular' => resource_path('fonts/Roboto-Regular.ttf'),
                'bold' => resource_path('fonts/Roboto-Bold.ttf'),
            ],
            self::FONT_LEXEND => [
                'regular' => resource_path('fonts/Lexend-Regular.ttf'),
                'bold' => resource_path('fonts/Lexend-Bold.ttf'),
            ],
            self::FONT_CORBEN => [
                'regular' => resource_path('fonts/Corben-Regular.ttf'),
                'bold' => resource_path('fonts/Corben-Bold.ttf'),
            ],
        ];
    }

    public static function defaultFontFamily(): string
    {
        return self::FONT_MONTSERRAT;
    }

    public function cardTemplate(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class);
    }

    public function isQrCode(): bool
    {
        return $this->placeholder_key === self::PLACEHOLDER_QR_CODE;
    }

    public function isTextPlaceholder(): bool
    {
        return ! $this->isQrCode();
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label
            ?: self::availablePlaceholders()[$this->placeholder_key]
            ?? ucfirst(str_replace('_', ' ', (string) $this->placeholder_key));
    }

    public function getPreviewValueAttribute(): string
    {
        return match ($this->placeholder_key) {
            self::PLACEHOLDER_NAME => 'Guest 1',
            self::PLACEHOLDER_CARD_TYPE => 'Single',
            self::PLACEHOLDER_SERIAL_NUMBER => 'ELV-2026-ABC123',
            self::PLACEHOLDER_GUEST_COUNT => '1 Guest',
            self::PLACEHOLDER_ALLOWED_GUESTS => '1',
            self::PLACEHOLDER_TABLE_NUMBER => 'A1',
            self::PLACEHOLDER_CATEGORY => 'Family',
            self::PLACEHOLDER_EVENT_NAME => 'Sample Send-off Event',
            self::PLACEHOLDER_EVENT_DATE => '24 Jun 2026',
            self::PLACEHOLDER_EVENT_TIME => '06:00 PM',
            self::PLACEHOLDER_EVENT_VENUE => 'Sample Hall, Dodoma',
            self::PLACEHOLDER_QR_CODE => 'QR Preview',
            default => $this->display_label,
        };
    }

    public function getFontFilePathAttribute(): ?string
    {
        $fontFamily = $this->font_family ?: self::defaultFontFamily();
        $fontWeight = $this->font_weight === 'bold' ? 'bold' : 'regular';

        return self::fontFiles()[$fontFamily][$fontWeight]
            ?? self::fontFiles()[self::defaultFontFamily()][$fontWeight]
            ?? null;
    }

    public function getCssStyleAttribute(): string
    {
        return collect([
            "left: {$this->x_percent}%",
            "top: {$this->y_percent}%",
            "width: {$this->width_percent}%",
            "height: {$this->height_percent}%",
            "font-size: {$this->font_size}px",
            "color: {$this->font_color}",
            "font-weight: {$this->font_weight}",
            "font-family: {$this->font_family}",
            "text-align: {$this->text_align}",
        ])->implode('; ');
    }

    public function getQrCssStyleAttribute(): string
    {
        return collect([
            "left: {$this->x_percent}%",
            "top: {$this->y_percent}%",
            "width: {$this->width_percent}%",
            "height: {$this->height_percent}%",
            'display: flex',
            'align-items: center',
            'justify-content: center',
            'overflow: hidden',
            'border-radius: 0',
            "background-color: {$this->resolved_qr_background_color}",
        ])->implode('; ');
    }

    public function getQrPreviewImageStyleAttribute(): string
    {
        return collect([
            'display: block',
            'width: 100%',
            'height: 100%',
            'object-fit: contain',
            'image-rendering: pixelated',
            'image-rendering: crisp-edges',
            "background-color: {$this->resolved_qr_background_color}",
            'padding: 2%',
            'box-sizing: border-box',
        ])->implode('; ');
    }

    public function getResolvedQrColorAttribute(): string
    {
        return self::normalizeHexColor(
            $this->qr_color,
            self::DEFAULT_QR_COLOR
        );
    }

    public function getResolvedQrBackgroundColorAttribute(): string
    {
        return self::normalizeHexColor(
            $this->qr_background_color,
            self::DEFAULT_QR_BACKGROUND_COLOR
        );
    }

    public function getQrContrastRatioAttribute(): float
    {
        return self::contrastRatio(
            $this->resolved_qr_color,
            $this->resolved_qr_background_color
        );
    }

    public function getHasSafeQrContrastAttribute(): bool
    {
        return $this->qr_contrast_ratio >= self::MIN_SAFE_QR_CONTRAST;
    }

    public function getQrContrastMessageAttribute(): string
    {
        return $this->has_safe_qr_contrast
            ? 'QR contrast is suitable for scanning.'
            : 'Increase the contrast between the QR color and its background.';
    }

    public function getQrStyleSummaryAttribute(): string
    {
        return sprintf(
            '%d px • %s × %s%% • %s on %s • Contrast %s:1',
            (int) ($this->qr_size ?: self::DEFAULT_QR_SIZE),
            number_format((float) $this->width_percent, 2),
            number_format((float) $this->height_percent, 2),
            $this->resolved_qr_color,
            $this->resolved_qr_background_color,
            number_format($this->qr_contrast_ratio, 2),
        );
    }

    public static function normalizeHexColor(
        ?string $color,
        string $fallback = '#111827'
    ): string {
        $color = strtoupper(trim((string) $color));

        if ($color === '') {
            return strtoupper($fallback);
        }

        if (! str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        if (preg_match('/^#[0-9A-F]{3}$/', $color)) {
            return '#'
                .$color[1].$color[1]
                .$color[2].$color[2]
                .$color[3].$color[3];
        }

        return preg_match('/^#[0-9A-F]{6}$/', $color)
            ? $color
            : strtoupper($fallback);
    }

    protected static function clampPercent(
        mixed $value,
        float $fallback,
        float $minimum = 0,
        float $maximum = 100
    ): float {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(
            $minimum,
            min($maximum, (float) $value)
        );
    }

    protected static function contrastRatio(
        string $foreground,
        string $background
    ): float {
        $foregroundLuminance = self::relativeLuminance($foreground);
        $backgroundLuminance = self::relativeLuminance($background);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return round(
            ($lighter + 0.05) / ($darker + 0.05),
            2
        );
    }

    protected static function relativeLuminance(string $hex): float
    {
        $hex = ltrim(self::normalizeHexColor($hex), '#');

        $components = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $components = array_map(
            static fn (float $component): float =>
                $component <= 0.03928
                    ? $component / 12.92
                    : (($component + 0.055) / 1.055) ** 2.4,
            $components,
        );

        return (0.2126 * $components[0])
            + (0.7152 * $components[1])
            + (0.0722 * $components[2]);
    }
}
