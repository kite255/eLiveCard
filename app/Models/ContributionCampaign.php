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

    protected $fillable = [
        'event_id',
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
            self::STATUS_ARCHIVED => 'Archived',
        ];
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
