<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardSmsBalanceService
{
    public const CACHE_KEY = 'elive.dashboard.sms_balance';

    /** Dashboard reads only cached data; never waits for the SMS gateway. */
    public function cached(): string
    {
        if (blank(config('services.sms_balance.url'))
            || blank(config('services.sms_balance.api_key'))
            || blank(config('services.sms_balance.api_secret'))) {
            return 'Not configured';
        }

        return (string) Cache::get(self::CACHE_KEY, 'Updating');
    }

    /** Run from the scheduler or explicitly via the console, not during page rendering. */
    public function refresh(): string
    {
        $url = config('services.sms_balance.url');
        $apiKey = config('services.sms_balance.api_key');
        $apiSecret = config('services.sms_balance.api_secret');

        if (blank($url) || blank($apiKey) || blank($apiSecret)) {
            return 'Not configured';
        }

        try {
            // Keep a bounded background request; retain the last successful balance on errors.
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'api_key' => (string) $apiKey,
                    'api_secret' => (string) $apiSecret,
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('SMS balance background refresh failed', ['status' => $response->status()]);

                return 'Unavailable';
            }

            $payload = $response->json();
            $balance = data_get($payload, 'data.totalSms')
                ?? data_get($payload, 'data.total_sms')
                ?? $this->extract($payload);

            if ($balance === null || ! is_numeric($balance)) {
                Log::warning('SMS balance value missing in provider response');

                return 'Check provider';
            }

            $formatted = number_format((float) $balance, 0);
            Cache::put(self::CACHE_KEY, $formatted, now()->addDay());

            return $formatted;
        } catch (\Throwable $exception) {
            report($exception);

            return 'Unavailable';
        }
    }

    private function extract(mixed $data): int|float|string|null
    {
        if (is_numeric($data)) {
            return $data;
        }

        if (! is_array($data)) {
            return null;
        }

        foreach (['balance', 'sms_balance', 'totalSms', 'total_sms', 'credits', 'credit', 'remaining', 'remaining_balance', 'available_balance'] as $key) {
            if (array_key_exists($key, $data) && is_numeric($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $key => $value) {
            if (in_array((string) $key, ['code', 'status', 'status_code'], true)) {
                continue;
            }

            $balance = $this->extract($value);
            if ($balance !== null) {
                return $balance;
            }
        }

        return null;
    }
}
