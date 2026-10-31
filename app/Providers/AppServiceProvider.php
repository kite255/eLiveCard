<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment(['production', 'staging'])) {
            URL::forceScheme('https');
        }

        // Record HTTP 419 failures automatically, even when detailed session
        // diagnostics are disabled. All other requests remain opt-in.
        // No raw session IDs, cookies, CSRF tokens, or request bodies are logged.
        $diagnosticsEnabled = getenv('ELIVE_SESSION_DIAGNOSTICS') === 'true';

        Event::listen(RequestHandled::class, function (RequestHandled $event) use ($diagnosticsEnabled): void {
            $request = $event->request;
            $status = $event->response->getStatusCode();

            if (! $request->is('admin', 'admin/*', 'livewire/update')) {
                return;
            }

            if (! $diagnosticsEnabled && $status !== 419) {
                return;
            }

            try {
                $session = $request->hasSession() ? $request->session() : null;
                $sessionId = $session?->getId();
                $sessionFingerprint = $sessionId
                    ? substr(hash_hmac('sha256', $sessionId, config('app.key')), 0, 16)
                    : null;

                $recordExists = null;

                if ($sessionId && config('session.driver') === 'database') {
                    $recordExists = DB::connection(config('session.connection'))
                        ->table(config('session.table', 'sessions'))
                        ->where('id', $sessionId)
                        ->exists();
                }

                Log::log($status === 419 ? 'warning' : 'info', 'elive.session_diagnostic', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'status' => $event->response->getStatusCode(),
                    'session_cookie_present' => $request->cookies->has(config('session.cookie')),
                    'has_session' => (bool) $session,
                    'session_fingerprint' => $sessionFingerprint,
                    'session_record_exists' => $recordExists,
                    'authenticated_user_id' => $request->user()?->getAuthIdentifier(),
                    'session_has_web_login' => $session
                        ? collect(array_keys($session->all()))
                            ->contains(fn ($key) => str_starts_with($key, 'login_web_'))
                        : false,
                    'https' => $request->isSecure(),
                ]);
            } catch (Throwable $e) {
                // Diagnostics must never interrupt or change authentication.
                Log::warning('elive.session_diagnostic_unavailable', [
                    'path' => $request->path(),
                    'error_type' => get_class($e),
                ]);
            }
        });
    }
}
