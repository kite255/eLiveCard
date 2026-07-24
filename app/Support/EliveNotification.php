<?php

namespace App\Support;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EliveNotification
{
    public const SUCCESS = 'success';

    public const WARNING = 'warning';

    public const DANGER = 'danger';

    public const INFO = 'info';

    /**
     * Display a modern success notification.
     */
    public static function success(
        string $title,
        ?string $body = null,
        Model|string|null $context = null,
        bool $persistent = false,
        ?int $duration = 6000,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        bool $openActionInNewTab = false,
    ): void {
        static::send(
            title: $title,
            body: $body,
            type: self::SUCCESS,
            context: $context,
            persistent: $persistent,
            duration: $duration,
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
            openActionInNewTab: $openActionInNewTab,
        );
    }

    /**
     * Display a warning notification.
     */
    public static function warning(
        string $title,
        ?string $body = null,
        Model|string|null $context = null,
        bool $persistent = false,
        ?int $duration = 9000,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        bool $openActionInNewTab = false,
    ): void {
        static::send(
            title: $title,
            body: $body,
            type: self::WARNING,
            context: $context,
            persistent: $persistent,
            duration: $duration,
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
            openActionInNewTab: $openActionInNewTab,
        );
    }

    /**
     * Display an important error notification.
     *
     * Danger notifications remain visible by default so administrators have
     * enough time to read the problem and take action.
     */
    public static function danger(
        string $title,
        ?string $body = null,
        Model|string|null $context = null,
        bool $persistent = true,
        ?int $duration = null,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        bool $openActionInNewTab = false,
    ): void {
        static::send(
            title: $title,
            body: $body,
            type: self::DANGER,
            context: $context,
            persistent: $persistent,
            duration: $duration,
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
            openActionInNewTab: $openActionInNewTab,
        );
    }

    /**
     * Display an informational notification.
     */
    public static function info(
        string $title,
        ?string $body = null,
        Model|string|null $context = null,
        bool $persistent = false,
        ?int $duration = 7000,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        bool $openActionInNewTab = false,
    ): void {
        static::send(
            title: $title,
            body: $body,
            type: self::INFO,
            context: $context,
            persistent: $persistent,
            duration: $duration,
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
            openActionInNewTab: $openActionInNewTab,
        );
    }

    /**
     * Build and send a branded eLive Card notification.
     */
    public static function send(
        string $title,
        ?string $body = null,
        string $type = self::SUCCESS,
        Model|string|null $context = null,
        bool $persistent = false,
        ?int $duration = null,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        bool $openActionInNewTab = false,
    ): void {
        $type = static::normalizeType($type);

        $notification = Notification::make()
            ->title(static::formatTitle($title))
            ->body(static::formatBody(
                body: $body,
                contextLabel: static::resolveContextLabel($context),
            ))
            ->icon(static::iconFor($type))
            ->iconColor(static::colorFor($type))
            ->color(static::colorFor($type));

        match ($type) {
            self::DANGER => $notification->danger(),
            self::WARNING => $notification->warning(),
            self::INFO => $notification->info(),
            default => $notification->success(),
        };

        if (
            filled($actionLabel)
            && filled($actionUrl)
        ) {
            $notification->actions([
                Action::make('primaryAction')
                    ->label(trim((string) $actionLabel))
                    ->button()
                    ->color(static::actionColorFor($type))
                    ->url(
                        trim((string) $actionUrl),
                        shouldOpenInNewTab: $openActionInNewTab,
                    )
                    ->close(),
            ]);
        }

        if ($persistent) {
            $notification->persistent();
        } else {
            $notification->duration(
                $duration ?? static::defaultDurationFor($type)
            );
        }

        $notification->send();
    }

    /**
     * Keep titles clean and prevent duplicate brand prefixes.
     */
    protected static function formatTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            $title = 'Notification';
        }

        if (Str::startsWith(Str::lower($title), 'elive card')) {
            return $title;
        }

        return 'eLive Card • '.$title;
    }

    /**
     * Format the notification content with the context first and the main
     * message underneath, similar to a modern confirmation card.
     */
    protected static function formatBody(
        ?string $body,
        ?string $contextLabel,
    ): ?string {
        $sections = [];

        if (filled($contextLabel)) {
            $sections[] = 'For: '.trim((string) $contextLabel);
        }

        if (filled($body)) {
            $sections[] = trim((string) $body);
        }

        return empty($sections)
            ? null
            : implode("\n\n", $sections);
    }

    /**
     * Resolve a useful context label from common eLive Card models.
     */
    protected static function resolveContextLabel(
        Model|string|null $context,
    ): ?string {
        if (blank($context)) {
            return null;
        }

        if (is_string($context)) {
            return trim($context);
        }

        foreach ([
            'title',
            'name',
            'event_name',
            'template_name',
            'serial_number',
            'subject',
        ] as $attribute) {
            $value = $context->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return Str::headline(class_basename($context))
            .' #'
            .$context->getKey();
    }

    /**
     * Accept only supported Filament notification types.
     */
    protected static function normalizeType(string $type): string
    {
        $type = Str::lower(trim($type));

        return in_array($type, [
            self::SUCCESS,
            self::WARNING,
            self::DANGER,
            self::INFO,
        ], true)
            ? $type
            : self::SUCCESS;
    }

    /**
     * Use modern outline icons that match the notification state.
     */
    protected static function iconFor(string $type): string
    {
        return match ($type) {
            self::DANGER => 'heroicon-o-x-circle',
            self::WARNING => 'heroicon-o-exclamation-triangle',
            self::INFO => 'heroicon-o-information-circle',
            default => 'heroicon-o-check-circle',
        };
    }

    protected static function colorFor(string $type): string
    {
        return match ($type) {
            self::DANGER => 'danger',
            self::WARNING => 'warning',
            self::INFO => 'info',
            default => 'success',
        };
    }

    /**
     * Keep action buttons aligned with eLive Card branding.
     */
    protected static function actionColorFor(string $type): string
    {
        return match ($type) {
            self::DANGER => 'danger',
            self::WARNING => 'warning',
            default => 'primary',
        };
    }

    protected static function defaultDurationFor(string $type): int
    {
        return match ($type) {
            self::WARNING => 9000,
            self::INFO => 7000,
            self::DANGER => 10000,
            default => 6000,
        };
    }
}
