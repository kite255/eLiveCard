<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteeConversation extends Model
{
    protected $fillable = [
        'event_id',
        'invitee_id',
        'sent_by',
        'channel',
        'direction',
        'from_phone',
        'to_phone',
        'message',
        'status',
        'provider_message_id',
        'provider_response',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }

    public function isWhatsApp(): bool
    {
        return $this->channel === 'whatsapp';
    }

    public function isSms(): bool
    {
        return $this->channel === 'sms';
    }
}