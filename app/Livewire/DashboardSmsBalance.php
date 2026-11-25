<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class DashboardSmsBalance extends Component
{
    public function mount(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && ($user->role === User::ROLE_SUPER_ADMIN || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())), 403);
    }

    public function render()
    {
        $this->authorizeAccess();

        return view('livewire.dashboard-sms-balance', ['balance' => $this->getSmsBalance()]);
    }

    public function placeholder(): string
    {
        return '<div class="elive-card elive-kpi" role="status" aria-label="Loading SMS balance"><div class="elive-kpi-label">SMS Balance</div><div class="elive-kpi-value">Loading…</div></div>';
    }

    protected function getSmsBalance(): string
    {
        return Cache::remember(
            'elive.dashboard.sms_balance',
            now()->addMinutes(5),
            function (): string {
                try {
                    $url = config('services.sms_balance.url');
                    $apiKey = config('services.sms_balance.api_key');
                    $apiSecret = config('services.sms_balance.api_secret');
                    $timeout = (int) min(8, max(1, (int) config('services.sms_balance.timeout', 8)));

                    if (blank($url) || blank($apiKey) || blank($apiSecret)) {
                        return 'Not configured';
                    }

                    $response = Http::timeout($timeout)
                        ->connectTimeout(3)
                        ->acceptJson()
                        ->withHeaders([
                            'api_key' => (string) $apiKey,
                            'api_secret' => (string) $apiSecret,
                        ])
                        ->get($url);

                    if (! $response->successful()) {
                        Log::warning('SMS balance request failed', [
                            'status' => $response->status(),
                            'body' => str($response->body())
                                ->limit(500)
                                ->toString(),
                        ]);

                        return 'Unavailable';
                    }

                    $payload = $response->json();

                    // eLive returns the balance as data.totalSms.
                    // Read that exact field first so the response code (200)
                    // is never mistaken for the SMS balance.
                    $balance = data_get($payload, 'data.totalSms')
                        ?? data_get($payload, 'data.total_sms')
                        ?? $this->extractSmsBalance($payload);

                    if ($balance === null) {
                        Log::warning(
                            'SMS balance value was not found in provider response',
                            [
                                'response' => $response->json(),
                            ],
                        );

                        return 'Check provider';
                    }

                    return number_format((float) $balance, 0);
                } catch (\Throwable $e) {
                    report($e);

                    return 'Unavailable';
                }
            },
        );
    }

    protected function extractSmsBalance(mixed $data): int|float|string|null
    {
        if (is_numeric($data)) {
            return $data;
        }

        if (! is_array($data)) {
            return null;
        }

      foreach ([
    'balance',
    'sms_balance',
    'totalSms',
    'total_sms',
    'credits',
    'credit',
    'remaining',
    'remaining_balance',
    'available_balance',
] as $key) {
            if (array_key_exists($key, $data) && is_numeric($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $key => $value) {
            // Ignore HTTP/provider metadata such as code => 200.
            if (in_array((string) $key, ['code', 'status', 'status_code'], true)) {
                continue;
            }

            $balance = $this->extractSmsBalance($value);

            if ($balance !== null) {
                return $balance;
            }
        }

        return null;
    }


}
