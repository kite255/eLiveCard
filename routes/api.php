<?php

use App\Http\Controllers\SmsDeliveryCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMS Delivery Callback
|--------------------------------------------------------------------------
| This endpoint receives delivery status updates from the SMS provider.
|
| URL:
| POST /api/sms/delivery-callback
*/

Route::post('/sms/delivery-callback', SmsDeliveryCallbackController::class)
    ->name('sms.delivery-callback');