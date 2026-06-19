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
| GET:
| Used by Meta to verify the webhook callback URL.
|
| POST:
| Receives incoming WhatsApp messages and delivery status updates such as
| sent, delivered, read, and failed.
|
| Staging callback:
| https://staging-digital.elive.co.tz/api/whatsapp/webhook
|
| Live callback:
| https://digital.elive.co.tz/api/whatsapp/webhook
|
| These routes must remain public. Do not place them inside auth middleware.
|
*/
Route::get(
    '/api/whatsapp/webhook',
    [WhatsAppWebhookController::class, 'verify']
)->name('whatsapp.webhook.verify');

Route::post(
    '/api/whatsapp/webhook',
    [WhatsAppWebhookController::class, 'handle']
)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('whatsapp.webhook.handle');

/*
|--------------------------------------------------------------------------
| Public Invitee Page
|--------------------------------------------------------------------------
| Main private invitation page.
|
| Example:
| Local: http://127.0.0.1:8002/i/NPTUIN
| Live:  https://digital.elive.co.tz/i/NPTUIN
*/
Route::get('/i/{shortCode}', [InviteePageController::class, 'show'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.page');

/*
|--------------------------------------------------------------------------
| Invitee RSVP From Private Page
|--------------------------------------------------------------------------
*/
Route::post('/i/{shortCode}/rsvp', [InviteePageController::class, 'rsvp'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.rsvp');

/*
|--------------------------------------------------------------------------
| Standalone RSVP Confirmation Page
|--------------------------------------------------------------------------
| Useful for SMS and WhatsApp reminder links.
|
| Example:
| Local: http://127.0.0.1:8002/rsvp/{token}
| Live:  https://digital.elive.co.tz/rsvp/{token}
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
| Invitees can view and download their personalized card.
|
| Example:
| View:     https://digital.elive.co.tz/card/ELV-2026-ZRKJ7A
| Download: https://digital.elive.co.tz/card/ELV-2026-ZRKJ7A/download
*/
Route::get('/card/{serialNumber}', [PublicCardController::class, 'show'])
    ->where('serialNumber', '[A-Za-z0-9\-]+')
    ->name('public.card.show');

Route::get('/card/{serialNumber}/download', [PublicCardController::class, 'download'])
    ->where('serialNumber', '[A-Za-z0-9\-]+')
    ->name('public.card.download');

/*
|--------------------------------------------------------------------------
| Authenticated Admin/User Routes
|--------------------------------------------------------------------------
| These routes require login.
*/
Route::middleware(['auth'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Invitee Sample Excel Download
    |--------------------------------------------------------------------------
    | Excel columns:
    | name, phone, card_type, allowed_guests, category, table_number
    |
    | Required:
    | name, phone, card_type
    |
    | Optional:
    | allowed_guests, category, table_number
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
    | This is used by the admin/card designer to visually place placeholders
    | such as name, QR code, serial number, guest count, and table number.
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
    | Simple scanner page for gate users.
    |
    | Example:
    | Local: https://127.0.0.1:8002/gate/events/6/check-in
    | Live:  https://digital.elive.co.tz/gate/events/6/check-in
    |
    | These routes are protected because only logged-in gate users/admin users
    | should scan, verify, and confirm invitee check-ins.
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
    | Existing Gate Check-in Submit
    |--------------------------------------------------------------------------
    | Keep this for your existing /gate/verify/{token} flow.
    */
    Route::post(
        '/gate/verify/{token}/check-in',
        [GateVerifyController::class, 'checkIn']
    )->name('gate.verify.check-in');
});

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
Route::get('/card-template-preview/{cardTemplate}', function (
    CardTemplate $cardTemplate
) {
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
        Storage::disk('public')->path(
            $cardTemplate->template_image
        )
    );
})->name('card-template.preview');

/*
|--------------------------------------------------------------------------
| Gate Verification Page
|--------------------------------------------------------------------------
| This route displays the scanned invitee verification page.
| The actual check-in action is protected by auth above.
|
| Example:
| https://digital.elive.co.tz/gate/verify/{token}
*/
Route::get('/gate/verify/{token}', [GateVerifyController::class, 'show'])
    ->name('gate.verify.show');