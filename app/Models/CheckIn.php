<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /*
    |--------------------------------------------------------------------------
    | Check-in Method Constants
    |--------------------------------------------------------------------------
    */
    public const METHOD_QR = 'qr';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_SERIAL = 'serial';
    public const METHOD_PHONE = 'phone';
    public const METHOD_NAME = 'name';
    public const METHOD_GATE_SCANNER = 'gate_scanner';

    protected $fillable = [
        'event_id',
        'invitee_id',
        'checked_in_by',
        'checkin_method',
        'guests_checked_in',
        'previous_checked_in_count',
        'remaining_guests',
        'status',
        'remarks',
        'checked_in_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'invitee_id' => 'integer',
        'checked_in_by' => 'integer',
        'guests_checked_in' => 'integer',
        'previous_checked_in_count' => 'integer',
        'remaining_guests' => 'integer',
        'checked_in_at' => 'datetime',
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

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Options
    |--------------------------------------------------------------------------
    */

    public static function statuses(): array
    {
        return [
            self::STATUS_SUCCESS => 'Success',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function methods(): array
    {
        return [
            self::METHOD_QR => 'QR Code',
            self::METHOD_MANUAL => 'Manual',
            self::METHOD_SERIAL => 'Serial Number',
            self::METHOD_PHONE => 'Phone Number',
            self::METHOD_NAME => 'Name Search',
            self::METHOD_GATE_SCANNER => 'Gate Scanner',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isManualCheckIn(): bool
    {
        return $this->checkin_method === self::METHOD_MANUAL;
    }

    public function isQrCheckIn(): bool
    {
        return $this->checkin_method === self::METHOD_QR;
    }

    public function isGateScannerCheckIn(): bool
    {
        return $this->checkin_method === self::METHOD_GATE_SCANNER;
    }

    public function checkedInByName(): string
    {
        return $this->checkedInBy?->name ?? 'System';
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function methodLabel(): string
    {
        return self::methods()[$this->checkin_method] ?? ucfirst((string) $this->checkin_method);
    }
}