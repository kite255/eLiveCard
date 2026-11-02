<?php

namespace App\Support;

use Filament\Notifications\Notification;

/**
 * Consistent admin toast feedback. Call only after the relevant operation
 * has succeeded or its queue submission has been confirmed.
 */
final class ActionToast
{
    public static function success(string $title, ?string $body = null): void
    {
        self::make($title, $body)->success()->duration(5000)->send();
    }

    public static function queued(string $title, ?string $body = null): void
    {
        self::make($title, $body)->info()->duration(5000)->send();
    }

    public static function warning(string $title, ?string $body = null): void
    {
        self::make($title, $body)->warning()->duration(8000)->send();
    }

    public static function failure(string $title, ?string $body = null): void
    {
        self::make($title, $body)->danger()->persistent()->send();
    }

    private static function make(string $title, ?string $body): Notification
    {
        $notification = Notification::make()->title($title);

        return $body === null ? $notification : $notification->body($body);
    }
}
