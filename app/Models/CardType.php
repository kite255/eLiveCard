<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardType extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'allowed_people',
        'description',
        'color',
        'is_active',
    ];

    protected $casts = [
        'allowed_people' => 'integer',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(Invitee::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute Helpers
    |--------------------------------------------------------------------------
    | Your database uses allowed_people and is_active.
    | These helpers allow the system to also read guests and status safely.
    */

    public function getGuestsAttribute(): int
    {
        return (int) ($this->allowed_people ?? 1);
    }

    public function setGuestsAttribute($value): void
    {
        $this->attributes['allowed_people'] = (int) $value;
    }

    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['is_active'] = $value === true || $value === 'active' || $value === '1' || $value === 1;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}