<?php

namespace App\Console\Commands;

use App\Services\DashboardSmsBalanceService;
use Illuminate\Console\Command;

class RefreshDashboardSmsBalance extends Command
{
    protected $signature = 'dashboard:refresh-sms-balance';

    protected $description = 'Refresh the cached SMS balance without blocking the dashboard';

    public function handle(DashboardSmsBalanceService $balance): int
    {
        $result = $balance->refresh();

        $this->info('SMS balance refresh: '.$result);

        return self::SUCCESS;
    }
}
