<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckWhatsAppConfiguration extends Command
{
    protected $signature = 'whatsapp:check-config';

    protected $description = 'Read-only WhatsApp sending readiness check for this container; never prints credentials';

    public function handle(): int
    {
        $checks = [
            'Sending enabled' => (bool) config('services.whatsapp.enabled', false),
            'Meta Cloud API driver' => config('services.whatsapp.driver') === 'meta_cloud_api',
            'Access token configured' => filled(config('services.whatsapp.access_token')),
            'Phone number ID configured' => filled(config('services.whatsapp.phone_number_id')),
        ];

        $this->line('Container WhatsApp readiness (effective Laravel configuration):');
        foreach ($checks as $name => $ok) {
            $this->line(sprintf('  %s: %s', $name, $ok ? 'OK' : 'MISSING / DISABLED'));
        }

        $this->line('Laravel config cached: '.(app()->configurationIsCached() ? 'yes' : 'no'));
        $this->line('This does not validate token expiry, template approval, or Meta connectivity.');

        if (in_array(false, $checks, true)) {
            $this->error('WhatsApp is not ready in this container. Correct Coolify environment variables and restart/redeploy this service.');

            return self::FAILURE;
        }

        $this->info('Local WhatsApp sending configuration is ready.');

        return self::SUCCESS;
    }
}
