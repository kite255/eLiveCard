<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;
use Throwable;
use UnitEnum;

class AuditLogService
{
    /**
     * Record a general audit-log entry.
     *
     * This is the central method used by all audit helpers.
     * Audit failures must never stop the main application workflow.
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
    ): ?AuditLog {
        try {
            $resolvedEventId = $eventId
                ?? ($subject ? self::resolveEventId($subject) : null);

            return AuditLog::record(
                action: self::normalizeAction($action),
                subject: $subject,
                eventId: $resolvedEventId,
                description: $description,
                oldValues: self::sanitizeValues($oldValues),
                newValues: self::sanitizeValues($newValues),
                metadata: self::sanitizeValues($metadata),
                userId: $userId,
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Record a newly created model.
     */
    public static function created(
        Model $subject,
        ?int $eventId = null,
        ?string $description = null,
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        return self::record(
            action: self::actionName($subject, 'created'),
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? class_basename($subject).' was created.',
            newValues: self::auditableAttributes($subject),
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Record changes made to an existing model.
     */
    public static function updated(
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?int $eventId = null,
        ?string $description = null,
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        if ($oldValues === [] && $newValues === []) {
            $oldValues = self::originalChangedValues($subject);
            $newValues = self::changedValues($subject);
        }

        return self::record(
            action: self::actionName($subject, 'updated'),
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? class_basename($subject).' was updated.',
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Record deletion before the model is removed.
     */
    public static function deleted(
        Model $subject,
        ?int $eventId = null,
        ?string $description = null,
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        return self::record(
            action: self::actionName($subject, 'deleted'),
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? class_basename($subject).' was deleted.',
            oldValues: self::auditableAttributes($subject),
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Record an approval action.
     */
    public static function approved(
        Model $subject,
        ?int $eventId = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        return self::record(
            action: self::actionName($subject, 'approved'),
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? class_basename($subject).' was approved.',
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Record a rejection action.
     */
    public static function rejected(
        Model $subject,
        ?int $eventId = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        return self::record(
            action: self::actionName($subject, 'rejected'),
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? class_basename($subject).' was rejected.',
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Record a message-send action.
     *
     * The channel is optional for backward compatibility. It may be passed
     * explicitly or supplied through metadata['channel'].
     */
    public static function messageSent(
        ?Model $subject = null,
        ?string $channel = null,
        ?int $eventId = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        $resolvedChannel = strtolower(trim(
            (string) (
                $channel
                ?? $metadata['channel']
                ?? $metadata['driver']
                ?? 'message'
            )
        ));

        $actionPrefix = match (true) {
            str_contains($resolvedChannel, 'whatsapp') => 'whatsapp',
            str_contains($resolvedChannel, 'sms') => 'sms',
            default => $subject
                ? str(class_basename($subject))->snake()->toString()
                : 'message',
        };

        return self::record(
            action: $actionPrefix.'.sent',
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? Str::headline($resolvedChannel).' message was sent.',
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: array_merge([
                'channel' => $resolvedChannel,
            ], $metadata),
            userId: $userId,
        );
    }

    /**
     * Record an invitee check-in.
     */
    public static function checkedIn(
        Model $subject,
        int $guestCount = 1,
        string $method = 'qr',
        ?int $eventId = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        return self::record(
            action: self::actionName($subject, 'checked_in'),
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? class_basename($subject).' checked in.',
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: array_merge([
                'guest_count' => $guestCount,
                'method' => $method,
            ], $metadata),
            userId: $userId,
        );
    }

    /**
     * Record an export.
     */
    public static function exported(
        ?Model $subject = null,
        ?int $eventId = null,
        ?string $description = null,
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        return self::record(
            action: $subject
                ? self::actionName($subject, 'exported')
                : 'export.completed',
            subject: $subject,
            eventId: $eventId,
            description: $description
                ?? 'A report or dataset was exported.',
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Record a system-level action without requiring a model subject.
     *
     * Namespaced actions such as "whatsapp.configuration_error" are kept
     * unchanged. Simple actions such as "queue_restarted" become
     * "system.queue_restarted".
     */
    public static function system(
        string $action,
        ?string $description = null,
        ?int $eventId = null,
        array $metadata = [],
        ?int $userId = null,
    ): ?AuditLog {
        $normalizedAction = self::normalizeAction($action);

        if (! str_contains($normalizedAction, '.')) {
            $normalizedAction = 'system.'.$normalizedAction;
        }

        return self::record(
            action: $normalizedAction,
            subject: null,
            eventId: $eventId,
            description: $description,
            metadata: $metadata,
            userId: $userId,
        );
    }

    /**
     * Capture only changed attributes from a model.
     */
    public static function changedValues(Model $subject): array
    {
        return self::sanitizeValues(
            $subject->getChanges()
        );
    }

    /**
     * Capture original values corresponding to changed attributes.
     */
    public static function originalChangedValues(Model $subject): array
    {
        $keys = array_keys(
            $subject->getChanges()
        );

        return self::sanitizeValues(
            Arr::only(
                $subject->getOriginal(),
                $keys
            )
        );
    }

    /**
     * Return a safe model attribute snapshot.
     */
    public static function auditableAttributes(Model $subject): array
    {
        return self::sanitizeValues(
            $subject->getAttributes()
        );
    }

    /**
     * Build a consistent action name such as invitee.updated.
     */
    protected static function actionName(
        Model $subject,
        string $verb
    ): string {
        $model = str(class_basename($subject))
            ->snake()
            ->toString();

        return self::normalizeAction(
            $model.'.'.$verb
        );
    }

    /**
     * Normalize action names for consistent filtering.
     */
    protected static function normalizeAction(string $action): string
    {
        $action = trim($action);

        if ($action === '') {
            return 'system.activity';
        }

        return str($action)
            ->lower()
            ->replace([' ', '-', '/'], '_')
            ->replaceMatches('/_+/', '_')
            ->replaceMatches('/\.+/', '.')
            ->trim('._')
            ->toString();
    }

    /**
     * Resolve the event ID from common subject models and relations.
     */
    protected static function resolveEventId(Model $subject): ?int
    {
        $eventId = $subject->getAttribute('event_id');

        if (filled($eventId)) {
            return (int) $eventId;
        }

        if ($subject instanceof Event) {
            return (int) $subject->getKey();
        }

        if (
            method_exists($subject, 'event')
            && $subject->relationLoaded('event')
            && $subject->getRelation('event') instanceof Event
        ) {
            return (int) $subject->getRelation('event')->getKey();
        }

        $invitee = $subject->relationLoaded('invitee')
            ? $subject->getRelation('invitee')
            : null;

        if (
            $invitee instanceof Model
            && filled($invitee->getAttribute('event_id'))
        ) {
            return (int) $invitee->getAttribute('event_id');
        }

        return null;
    }

    /**
     * Remove sensitive values recursively and normalize values for JSON.
     */
    protected static function sanitizeValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = self::sanitizeValue($value);
        }

        return $sanitized;
    }

    /**
     * Normalize one nested value.
     */
    protected static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::sanitizeValues($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Arrayable) {
            return self::sanitizeValues($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            $serialized = $value->jsonSerialize();

            return is_array($serialized)
                ? self::sanitizeValues($serialized)
                : self::sanitizeValue($serialized);
        }

        if ($value instanceof Model) {
            return [
                'type' => class_basename($value),
                'id' => $value->getKey(),
            ];
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        if (is_object($value)) {
            return [
                'type' => class_basename($value),
            ];
        }

        return $value;
    }

    /**
     * Detect sensitive keys, including nested provider payload fields.
     */
    protected static function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(
            str_replace(['-', '.', ' '], '_', $key)
        );

        $sensitivePatterns = [
            'password',
            'password_confirmation',
            'remember_token',
            'qr_token_hash',
            'api_token',
            'access_token',
            'refresh_token',
            'authorization',
            'auth_token',
            'bearer_token',
            'client_secret',
            'app_secret',
            'webhook_secret',
            'signing_secret',
            'private_key',
            'secret',
            'token',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (
                $normalizedKey === $pattern
                || str_ends_with($normalizedKey, '_'.$pattern)
                || str_starts_with($normalizedKey, $pattern.'_')
            ) {
                return true;
            }
        }

        return false;
    }
}
