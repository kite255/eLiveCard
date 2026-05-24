<?php

use App\Http\Controllers\GateVerifyController;
use App\Http\Controllers\InviteePageController;
use App\Http\Controllers\RsvpController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public Invitee Page
|--------------------------------------------------------------------------
| Short QR / private invitation link:
| Local: http://127.0.0.1:8002/i/NPTUIN
| Live:  https://card.elive.co.tz/i/NPTUIN
|
| This is the main invitee page. Later, RSVP can be fully merged here.
*/
Route::get('/i/{shortCode}', [InviteePageController::class, 'show'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.page');

/*
|--------------------------------------------------------------------------
| Invitee RSVP Action From Private Invitee Page
|--------------------------------------------------------------------------
| This route receives RSVP button clicks from the private invitee page.
| Example:
| - I will attend
| - I will not attend
*/
Route::post('/i/{shortCode}/rsvp', [InviteePageController::class, 'rsvp'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.rsvp');

/*
|--------------------------------------------------------------------------
| Standalone RSVP Confirmation Page
|--------------------------------------------------------------------------
| Private RSVP link:
| Local: http://127.0.0.1:8002/rsvp/{token}
| Live:  https://card.elive.co.tz/rsvp/{token}
|
| This is useful for SMS reminders and direct RSVP confirmation.
*/
Route::get('/rsvp/{token}', [RsvpController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('rsvp.show');

Route::post('/rsvp/{token}', [RsvpController::class, 'submit'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('rsvp.submit');

Route::get('/rsvp/{token}/thank-you', [RsvpController::class, 'thankYou'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('rsvp.thank-you');

/*
|--------------------------------------------------------------------------
| Gate Verification
|--------------------------------------------------------------------------
| This route is for gate users to verify scanned QR/token and check in guests.
*/
Route::get('/gate/verify/{token}', [GateVerifyController::class, 'show'])
    ->name('gate.verify.show');

Route::post('/gate/verify/{token}/check-in', [GateVerifyController::class, 'checkIn'])
    ->middleware('auth')
    ->name('gate.verify.check-in');