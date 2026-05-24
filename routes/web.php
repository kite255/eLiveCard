<?php

use App\Http\Controllers\GateVerifyController;
use App\Http\Controllers\InviteePageController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\RsvpController;
use App\Models\CardTemplate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
| This is the main invitee page where invitees can view invitation details,
| RSVP, see their QR code, serial number, guest count, and event information.
*/
Route::get('/i/{shortCode}', [InviteePageController::class, 'show'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.page');

/*
|--------------------------------------------------------------------------
| Invitee RSVP Action From Private Invitee Page
|--------------------------------------------------------------------------
| This route receives RSVP button clicks from the private invitee page.
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
| Public Generated Invitation Card
|--------------------------------------------------------------------------
| This allows invitees to view and download their personalized generated card.
|
| Example:
| View:     https://card.elive.co.tz/card/ELV-2026-ZRKJ7A
| Download: https://card.elive.co.tz/card/ELV-2026-ZRKJ7A/download
|
| This link can be sent by SMS or WhatsApp together with the invitee page link.
*/
Route::get('/card/{serialNumber}', [PublicCardController::class, 'show'])
    ->where('serialNumber', '[A-Za-z0-9\-]+')
    ->name('public.card.show');

Route::get('/card/{serialNumber}/download', [PublicCardController::class, 'download'])
    ->where('serialNumber', '[A-Za-z0-9\-]+')
    ->name('public.card.download');

/*
|--------------------------------------------------------------------------
| Card Template Preview
|--------------------------------------------------------------------------
| This route serves uploaded card template images through Laravel.
| It fixes staging/server cases where direct /storage/card-templates access
| returns 403 Forbidden even when the file exists.
|
| Example:
| https://staging-digital.elive.co.tz/card-template-preview/1
*/
Route::get('/card-template-preview/{cardTemplate}', function (CardTemplate $cardTemplate) {
    abort_if(! $cardTemplate->template_image, 404, 'Template image is missing.');

    abort_if(
        ! Storage::disk('public')->exists($cardTemplate->template_image),
        404,
        'Template image file was not found.'
    );

    return response()->file(
        Storage::disk('public')->path($cardTemplate->template_image)
    );
})->name('card-template.preview');

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