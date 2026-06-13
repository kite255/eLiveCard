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
            if (blank($placeholder->label) && filled($placeholder->placeholder_key)) {
                $placeholder->label = self::availablePlaceholders()[$placeholder->placeholder_key]
                    ?? ucfirst(str_replace('_', ' ', $placeholder->placeholder_key));
            }

            if (blank($placeholder->font_size)) {
                $placeholder->font_size = 32;
            }

            if (blank($placeholder->font_color)) {
                $placeholder->font_color = '#111827';
            }

            if (blank($placeholder->font_weight)) {
                $placeholder->font_weight = 'normal';
            }

            if (blank($placeholder->font_family)) {
                $placeholder->font_family = self::defaultFontFamily();
            }

            if (blank($placeholder->text_align)) {
                $placeholder->text_align = 'center';
            }

            if (blank($placeholder->qr_size)) {
                $placeholder->qr_size = 160;
            }

            if (blank($placeholder->qr_color)) {
                $placeholder->qr_color = '#111827';
            }

            if (blank($placeholder->qr_background_color)) {
                $placeholder->qr_background_color = '#FFFFFF';
            }

            if (is_null($placeholder->is_visible)) {
                $placeholder->is_visible = true;
            }

            if (blank($placeholder->sort_order)) {
                $placeholder->sort_order = 1;
            }

            if (blank($placeholder->x_percent)) {
                $placeholder->x_percent = 50;
            }

            if (blank($placeholder->y_percent)) {
                $placeholder->y_percent = 50;
            }

            if (blank($placeholder->width_percent)) {
                $placeholder->width_percent = $placeholder->isQrCode() ? 18 : 60;
            }

            if (blank($placeholder->height_percent)) {
                $placeholder->height_percent = $placeholder->isQrCode() ? 18 : 8;
            }
        });
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
            ?? ucfirst(str_replace('_', ' ', $this->placeholder_key));
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
            self::PLACEHOLDER_QR_CODE => 'QR Code',
            default => $this->display_label,
        };
    }

    public function getFontFilePathAttribute(): ?string
    {
        $fontFamily = $this->font_family ?: self::defaultFontFamily();
        $fontWeight = $this->font_weight === 'bold' ? 'bold' : 'regular';

        return self::fontFiles()[$fontFamily][$fontWeight] ?? null;
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
        ])->implode('; ');
    }
}