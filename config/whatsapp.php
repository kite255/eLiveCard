<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */

    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default invitation template
    |--------------------------------------------------------------------------
    */

    'invitation_template' => env(
        'WHATSAPP_INVITATION_TEMPLATE',
        'event_invitation_sw'
    ),

    'template_language' => env(
        'WHATSAPP_TEMPLATE_LANGUAGE',
        'sw'
    ),

    /*
    |--------------------------------------------------------------------------
    | API endpoint
    |--------------------------------------------------------------------------
    */

    'base_url' => env(
        'WHATSAPP_BASE_URL',
        'https://graph.facebook.com'
    ),

    /*
    |--------------------------------------------------------------------------
    | Request settings
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),

    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 10),

];