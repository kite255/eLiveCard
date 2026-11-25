<?php

namespace Tests\Feature;

use Tests\TestCase;

class WhatsAppConfigurationCheckTest extends TestCase
{
    public function test_disabled_whatsapp_causes_readiness_check_to_fail(): void
    {
        config()->set('services.whatsapp.enabled', false);
        config()->set('services.whatsapp.driver', 'meta_cloud_api');
        config()->set('services.whatsapp.access_token', 'fake-token');
        config()->set('services.whatsapp.phone_number_id', '123456');

        $this->artisan('whatsapp:check-config')
            ->expectsOutputToContain('Sending enabled: MISSING / DISABLED')
            ->assertFailed();
    }

    public function test_missing_credentials_causes_readiness_check_to_fail(): void
    {
        config()->set('services.whatsapp.enabled', true);
        config()->set('services.whatsapp.driver', 'meta_cloud_api');
        config()->set('services.whatsapp.access_token', null);
        config()->set('services.whatsapp.phone_number_id', null);

        $this->artisan('whatsapp:check-config')
            ->expectsOutputToContain('Access token configured: MISSING / DISABLED')
            ->assertFailed();
    }

    public function test_valid_local_configuration_passes_without_sending_messages(): void
    {
        config()->set('services.whatsapp.enabled', true);
        config()->set('services.whatsapp.driver', 'meta_cloud_api');
        config()->set('services.whatsapp.access_token', 'fake-token');
        config()->set('services.whatsapp.phone_number_id', '123456');

        $this->artisan('whatsapp:check-config')
            ->expectsOutputToContain('Local WhatsApp sending configuration is ready.')
            ->assertSuccessful();
    }
}
