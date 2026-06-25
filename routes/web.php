<?php

use App\Exports\InviteeSampleExport;
use App\Http\Controllers\CardTemplateDesignerController;
use App\Http\Controllers\GateCheckInController;
use App\Http\Controllers\GateVerifyController;
use App\Http\Controllers\InviteePageController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\RsvpController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\CardTemplate;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| WhatsApp Cloud API Webhook
|--------------------------------------------------------------------------
|
| These routes must remain public.
|
| Staging callback:
| https://staging-digital.elive.co.tz/api/whatsapp/webhook
|
| Live callback:
| https://digital.elive.co.tz/api/whatsapp/webhook
|
*/
Route::get('/api/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])
    ->name('whatsapp.webhook.verify');

Route::post('/api/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('whatsapp.webhook.handle');

/*
|--------------------------------------------------------------------------
| Public Invitee Page
|--------------------------------------------------------------------------
|
| Example:
| Local: http://127.0.0.1:8002/i/NPTUIN
| Live:  https://digital.elive.co.tz/i/NPTUIN
|
*/
Route::get('/i/{shortCode}', [InviteePageController::class, 'show'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.page');

Route::post('/i/{shortCode}/rsvp', [InviteePageController::class, 'rsvp'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.rsvp');

/*
|--------------------------------------------------------------------------
| Standalone RSVP Confirmation Page
|--------------------------------------------------------------------------
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
|
| Example:
| View:     https://digital.elive.co.tz/card/ELV-2026-ZRKJ7A
| Download: https://digital.elive.co.tz/card/ELV-2026-ZRKJ7A/download
|
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
|
| This route serves uploaded card template images through Laravel.
| It helps when direct /storage access returns 403 Forbidden.
|
*/
Route::get('/card-template-preview/{cardTemplate}', function (CardTemplate $cardTemplate) {
    abort_if(
        ! $cardTemplate->template_image,
        404,
        'Template image is missing.'
    );

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
| Public Gate Verification Page
|--------------------------------------------------------------------------
|
| This page displays the scanned invitee verification result.
| It can be public because the QR code itself contains the secure token.
| The actual check-in submit is protected by auth below.
|
| Example:
| https://digital.elive.co.tz/gate/verify/{token}
|
*/
Route::get('/gate/verify/{token}', [GateVerifyController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('gate.verify.show');

/*
|--------------------------------------------------------------------------
| Authenticated Admin/User Routes
|--------------------------------------------------------------------------
|
| These routes require login.
|
*/
Route::middleware(['auth'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Invitee Sample Excel Download
    |--------------------------------------------------------------------------
    */
    Route::get('/invitees/sample-excel', function () {
        return Excel::download(
            new InviteeSampleExport(),
            'elive-card-invitees-sample.xlsx'
        );
    })->name('invitees.sample-excel');

    /*
    |--------------------------------------------------------------------------
    | Drag-and-Drop Card Template Designer
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/admin/card-templates/{cardTemplate}/designer',
        [CardTemplateDesignerController::class, 'show']
    )->name('card-templates.designer');

    Route::post(
        '/admin/card-templates/{cardTemplate}/designer/save',
        [CardTemplateDesignerController::class, 'save']
    )->name('card-templates.designer.save');

    Route::post(
        '/admin/card-templates/{cardTemplate}/designer/placeholders',
        [CardTemplateDesignerController::class, 'createPlaceholder']
    )->name('card-templates.designer.placeholders.create');

    Route::delete(
        '/admin/card-templates/{cardTemplate}/designer/placeholders/{placeholder}',
        [CardTemplateDesignerController::class, 'deletePlaceholder']
    )->name('card-templates.designer.placeholders.delete');

    /*
    |--------------------------------------------------------------------------
    | Professional Gate Check-in Page
    |--------------------------------------------------------------------------
    |
    | This is the main mobile-friendly scanner page for gate users.
    |
    | Example:
    | Local: http://127.0.0.1:8002/gate/events/6/check-in
    | Live:  https://digital.elive.co.tz/gate/events/6/check-in
    |
    */
    Route::get(
        '/gate/events/{event}/check-in',
        [GateCheckInController::class, 'show']
    )->name('gate.check-in.show');

    Route::post(
        '/gate/events/{event}/verify',
        [GateCheckInController::class, 'verify']
    )->name('gate.check-in.verify');

    Route::post(
        '/gate/events/{event}/confirm',
        [GateCheckInController::class, 'confirm']
    )->name('gate.check-in.confirm');

    /*
    |--------------------------------------------------------------------------
    | QR Token Check-in Submit
    |--------------------------------------------------------------------------
    |
    | This submits check-in from /gate/verify/{token}.
    |
    */
    Route::post(
        '/gate/verify/{token}/check-in',
        [GateVerifyController::class, 'checkIn']
    )
        ->where('token', '[A-Za-z0-9]+')
        ->name('gate.verify.check-in');
});