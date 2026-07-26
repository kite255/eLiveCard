<?php

use App\Http\Controllers\SmsDeliveryCallbackController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are automatically prefixed with /api.
| Keep provider callbacks here because API routes are stateless and do not
| require CSRF tokens or Laravel session cookies.
|
*/

/*
|--------------------------------------------------------------------------
| WhatsApp Cloud API Webhook
|--------------------------------------------------------------------------
|
| Meta uses the GET route to verify the webhook.
| Meta uses the POST route to send message status updates, button replies,
| incoming messages, delivered/read events, and failed delivery events.
|
| Callback URL:
| https://digital.elive.co.tz/api/whatsapp/webhook
|
*/

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])
    ->name('whatsapp.webhook.verify');

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->name('whatsapp.webhook.handle');

/*
|--------------------------------------------------------------------------
| SMS Delivery Callback
|--------------------------------------------------------------------------
|
| This endpoint receives delivery status updates from the SMS provider.
|
| Callback URL:
| https://digital.elive.co.tz/api/sms/delivery-callback
|
*/

Route::post('/sms/delivery-callback', SmsDeliveryCallbackController::class)
    ->name('sms.delivery-callback');