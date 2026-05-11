<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'event_type',
        'event_date',
        'start_time',
        'end_time',
        'venue_name',
        'venue_address',
        'google_maps_link',
        'dress_code',
        'program',
        'contact_person_name',
        'contact_person_phone',
        'status',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cardTypes(): HasMany
    {
        return $this->hasMany(CardType::class);
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(Invitee::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }
}