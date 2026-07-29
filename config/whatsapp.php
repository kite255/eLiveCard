<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */

    'enabled' => filter_var(
        env('WHATSAPP_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),

    'driver' => env(
        'WHATSAPP_DRIVER',
        'log'
    ),

    'api_version' => env(
        'WHATSAPP_API_VERSION',
        'v25.0'
    ),

    'base_url' => rtrim(
        env(
            'WHATSAPP_API_URL',
            'https://graph.facebook.com'
        ),
        '/'
    ),

    /*
    |--------------------------------------------------------------------------
    | Meta account details
    |--------------------------------------------------------------------------
    */

    'app_id' => env(
        'WHATSAPP_APP_ID'
    ),

    'business_id' => env(
        'WHATSAPP_BUSINESS_ID'
    ),

    'business_account_id' => env(
        'WHATSAPP_BUSINESS_ACCOUNT_ID',
        env('WHATSAPP_BUSINESS_ID')
    ),

    'phone_number_id' => env(
        'WHATSAPP_PHONE_NUMBER_ID'
    ),

    'display_phone_number' => env(
        'WHATSAPP_DISPLAY_PHONE_NUMBER'
    ),

    /*
    |--------------------------------------------------------------------------
    | API credentials
    |--------------------------------------------------------------------------
    */

    'access_token' => env(
        'WHATSAPP_ACCESS_TOKEN'
    ),

    'app_secret' => env(
        'WHATSAPP_APP_SECRET'
    ),

    /*
    |--------------------------------------------------------------------------
    | Webhook configuration
    |--------------------------------------------------------------------------
    */

    'webhook_verify_token' => env(
        'WHATSAPP_WEBHOOK_VERIFY_TOKEN'
    ),

    'verify_webhook_signature' => filter_var(
        env(
            'WHATSAPP_VERIFY_WEBHOOK_SIGNATURE',
            true
        ),
        FILTER_VALIDATE_BOOL
    ),

    /*
    |--------------------------------------------------------------------------
    | Default invitation template
    |--------------------------------------------------------------------------
    */

    'invitation_template' => env(
        'WHATSAPP_TEMPLATE_INVITATION',
        'event_invitation_en'
    ),

    'templates' => [
        'invitation' => env(
            'WHATSAPP_TEMPLATE_INVITATION',
            'event_invitation_en'
        ),
    ],

    'template_language' => env(
        'WHATSAPP_TEMPLATE_LANGUAGE',
        'en'
    ),

    /*
    |--------------------------------------------------------------------------
    | Invitation image
    |--------------------------------------------------------------------------
    */

    'default_invitation_image_url' => env(
        'WHATSAPP_DEFAULT_INVITATION_IMAGE_URL'
    ),

    /*
    |--------------------------------------------------------------------------
    | Request settings
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env(
        'WHATSAPP_TIMEOUT',
        30
    ),

    'connect_timeout' => (int) env(
        'WHATSAPP_CONNECT_TIMEOUT',
        10
    ),

    'retry_times' => (int) env(
        'WHATSAPP_RETRY_TIMES',
        2
    ),

    'retry_delay' => (int) env(
        'WHATSAPP_RETRY_DELAY',
        500
    ),

];