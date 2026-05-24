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
            self::PLACEHOLDER_NAME => 'John Doe',
            self::PLACEHOLDER_CARD_TYPE => 'VIP',
            self::PLACEHOLDER_SERIAL_NUMBER => 'ELC-0001',
            self::PLACEHOLDER_GUEST_COUNT => '2 Guests',
            self::PLACEHOLDER_ALLOWED_GUESTS => '2',
            self::PLACEHOLDER_TABLE_NUMBER => 'Table 5',
            self::PLACEHOLDER_CATEGORY => 'Family',
            self::PLACEHOLDER_EVENT_NAME => 'Wedding Ceremony',
            self::PLACEHOLDER_EVENT_DATE => '25 Dec 2026',
            self::PLACEHOLDER_EVENT_TIME => '04:00 PM',
            self::PLACEHOLDER_EVENT_VENUE => 'Royal Hall',
            self::PLACEHOLDER_QR_CODE => 'QR Code',
            default => $this->display_label,
        };
    }
}