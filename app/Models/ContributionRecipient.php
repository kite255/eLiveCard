<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContributionRecipient extends Model
{
    public const STATUS_NOT_SENT = 'not_sent';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'contribution_campaign_id',
        'event_id',
        'name',
        'phone',
        'generated_card_path',
        'generation_status',
        'send_status',
        'whatsapp_status',
        'provider_message_id',
        'last_error',
        'generated_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'contribution_campaign_id' => 'integer',
        'event_id' => 'integer',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ContributionCampaign::class, 'contribution_campaign_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getGeneratedCardUrlAttribute(): ?string
    {
        return filled($this->generated_card_path)
            ? Storage::disk('public')->url($this->generated_card_path)
            : null;
    }
}
