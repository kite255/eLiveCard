<?php

use App\Http\Controllers\SmsDeliveryCallbackController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes in this file automatically receive the "/api" prefix.
|
| Webhook and provider callback endpoints are placed here because API
| routes are stateless and do not require session cookies or CSRF tokens.
|
*/

/*
|--------------------------------------------------------------------------
| WhatsApp Cloud API
|--------------------------------------------------------------------------
|
| Verification:
| GET https://digital.elive.co.tz/api/whatsapp/webhook
|
| Notifications:
| POST https://digital.elive.co.tz/api/whatsapp/webhook
|
| Meta uses the GET route to verify the callback URL and verify token.
| Meta uses the POST route for:
|
| - Incoming WhatsApp messages
| - Template quick-reply responses
| - Interactive button responses
| - Message sent status
| - Message delivered status
| - Message read status
| - Message failed status
|
*/

Route::prefix('whatsapp')
    ->name('whatsapp.')
    ->controller(WhatsAppWebhookController::class)
    ->group(function (): void {
        Route::get('/webhook', 'verify')
            ->name('webhook.verify');

        Route::post('/webhook', 'handle')
            ->name('webhook.handle');
    });

/*
|--------------------------------------------------------------------------
| SMS Provider Callbacks
|--------------------------------------------------------------------------
|
| Delivery callback:
| POST https://digital.elive.co.tz/api/sms/delivery-callback
|
| The configured SMS provider sends delivery-status updates to this route.
|
*/

Route::prefix('sms')
    ->name('sms.')
    ->group(function (): void {
        Route::post(
            '/delivery-callback',
            SmsDeliveryCallbackController::class
        )->name('delivery-callback');
    });