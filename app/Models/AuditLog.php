<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Throwable;

class AuditLog extends Model
{
    use HasFactory;

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_SENT = 'sent';
    public const ACTION_CHECKED_IN = 'checked_in';
    public const ACTION_EXPORTED = 'exported';

    /**
     * Audit records are immutable application history.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'event_id' => 'integer',
        'subject_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected $appends = [
        'action_label',
        'subject_label',
        'action_group',
    ];

    /**
     * Audit logs should not themselves be audited by model observers.
     */
    public static bool $disableAudit = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForEvent(
        Builder $query,
        int $eventId
    ): Builder {
        return $query->where(
            'event_id',
            $eventId
        );
    }

    public function scopeForUser(
        Builder $query,
        int $userId
    ): Builder {
        return $query->where(
            'user_id',
            $userId
        );
    }

    public function scopeAction(
        Builder $query,
        string $action
    ): Builder {
        return $query->where(
            'action',
            $action
        );
    }

    public function scopeModule(
        Builder $query,
        string $module
    ): Builder {
        $module = trim(
            strtolower($module),
            " ._\t\n\r\0\x0B"
        );

        return $query->where(
            'action',
            'like',
            $module.'.%'
        );
    }

    public function scopeSystemActions(
        Builder $query
    ): Builder {
        return $query->whereNull('user_id');
    }

    public function scopeBetweenDates(
        Builder $query,
        mixed $from = null,
        mixed $until = null
    ): Builder {
        return $query
            ->when(
                $from,
                fn (Builder $query, $date): Builder =>
                    $query->whereDate(
                        'created_at',
                        '>=',
                        $date
                    )
            )
            ->when(
                $until,
                fn (Builder $query, $date): Builder =>
                    $query->whereDate(
                        'created_at',
                        '<=',
                        $date
                    )
            );
    }

    public function scopeRecent(
        Builder $query
    ): Builder {
        return $query->latest('created_at');
    }

    public function getActionLabelAttribute(): string
    {
        return str(
            $this->action ?? 'activity'
        )
            ->replace(
                ['.', '_', '-'],
                ' '
            )
            ->headline()
            ->toString();
    }

    public function getSubjectLabelAttribute(): string
    {
        if (blank($this->subject_type)) {
            return 'System';
        }

        return class_basename(
            $this->subject_type
        );
    }

    public function getActionGroupAttribute(): string
    {
        $group = str(
            $this->action ?? 'system.activity'
        )
            ->before('.')
            ->replace('_', ' ')
            ->headline()
            ->toString();

        return $group !== ''
            ? $group
            : 'System';
    }

    /**
     * Create an audit record without firing model events.
     *
     * Using withoutEvents prevents generic model observers from trying to
     * audit the AuditLog model itself and creating an infinite loop.
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?int $eventId = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $userId = null,
    ): self {
        $resolvedUserId = $userId
            ?? self::authenticatedUserId();

        $requestContext = self::requestContext();

        return static::withoutEvents(
            fn (): self => static::query()->create([
                'user_id' => $resolvedUserId,
                'event_id' => $eventId,
                'action' => self::normalizeAction($action),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'description' => filled($description)
                    ? trim((string) $description)
                    : null,
                'old_values' => $oldValues !== []
                    ? $oldValues
                    : null,
                'new_values' => $newValues !== []
                    ? $newValues
                    : null,
                'metadata' => $metadata !== []
                    ? $metadata
                    : null,
                'ip_address' => $requestContext['ip_address'],
                'user_agent' => $requestContext['user_agent'],
            ])
        );
    }

    protected static function authenticatedUserId(): ?int
    {
        try {
            $id = auth()->id();

            return filled($id)
                ? (int) $id
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function requestContext(): array
    {
        try {
            if (
                app()->runningInConsole()
                || ! app()->bound('request')
            ) {
                return [
                    'ip_address' => null,
                    'user_agent' => self::consoleUserAgent(),
                ];
            }

            $request = request();

            return [
                'ip_address' => filled($request->ip())
                    ? Str::limit(
                        (string) $request->ip(),
                        45,
                        ''
                    )
                    : null,

                'user_agent' => filled($request->userAgent())
                    ? Str::limit(
                        (string) $request->userAgent(),
                        1000,
                        ''
                    )
                    : null,
            ];
        } catch (Throwable) {
            return [
                'ip_address' => null,
                'user_agent' => null,
            ];
        }
    }

    protected static function consoleUserAgent(): string
    {
        if (app()->runningInConsole()) {
            return 'Laravel Console / Queue';
        }

        return 'System';
    }

    protected static function normalizeAction(
        string $action
    ): string {
        $action = trim($action);

        if ($action === '') {
            return 'system.activity';
        }

        return str($action)
            ->lower()
            ->replace(
                [' ', '-', '/'],
                '_'
            )
            ->replaceMatches(
                '/_+/',
                '_'
            )
            ->replaceMatches(
                '/\.+/',
                '.'
            )
            ->trim('._')
            ->toString();
    }
}
