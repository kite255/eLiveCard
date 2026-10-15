<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'event_id',
        'created_by',
        'campaign_type',
        'description',
        'deadline',
        'name',
        'template_image',
        'name_x_percent',
        'name_y_percent',
        'name_width_percent',
        'name_font_size',
        'name_font_color',
        'name_font_family',
        'name_font_weight',
        'name_text_align',
        'whatsapp_template_name',
        'whatsapp_language_code',
        'status',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'created_by' => 'integer',
        'deadline' => 'date',
        'name_x_percent' => 'float',
        'name_y_percent' => 'float',
        'name_width_percent' => 'float',
        'name_font_size' => 'integer',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public static function whatsappTemplateOptions(): array
    {
        return [
            'contribution_card_sw' => 'Contribution Card (Swahili) – personalized member name',
            'contribution_plain' => 'Contribution Plain (English) – image and fixed text',
        ];
    }

    public static function types(): array
    {
        return ['wedding' => 'Wedding', 'church' => 'Church', 'family' => 'Family', 'community' => 'Community', 'other' => 'Other'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ContributionRecipient::class);
    }
}
