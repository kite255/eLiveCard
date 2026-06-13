<?php

use App\Exports\InviteeSampleExport;
use App\Http\Controllers\CardTemplateDesignerController;
use App\Http\Controllers\GateVerifyController;
use App\Http\Controllers\InviteePageController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\RsvpController;
use App\Models\CardTemplate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public Invitee Page
|--------------------------------------------------------------------------
| Main private invitation page.
|
| Example:
| Local: http://127.0.0.1:8002/i/NPTUIN
| Live:  https://card.elive.co.tz/i/NPTUIN
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
| Live:  https://card.elive.co.tz/rsvp/{token}
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
| View:     https://card.elive.co.tz/card/ELV-2026-ZRKJ7A
| Download: https://card.elive.co.tz/card/ELV-2026-ZRKJ7A/download
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
    Route::get('/admin/card-templates/{cardTemplate}/designer', [CardTemplateDesignerController::class, 'show'])
        ->name('card-templates.designer');

    Route::post('/admin/card-templates/{cardTemplate}/designer/save', [CardTemplateDesignerController::class, 'save'])
        ->name('card-templates.designer.save');

    Route::post('/admin/card-templates/{cardTemplate}/designer/placeholders', [CardTemplateDesignerController::class, 'createPlaceholder'])
        ->name('card-templates.designer.placeholders.create');

    Route::delete('/admin/card-templates/{cardTemplate}/designer/placeholders/{placeholder}', [CardTemplateDesignerController::class, 'deletePlaceholder'])
        ->name('card-templates.designer.placeholders.delete');

    /*
    |--------------------------------------------------------------------------
    | Gate Check-in Submit
    |--------------------------------------------------------------------------
    | Only logged-in gate users/admin users should check in invitees.
    */
    Route::post('/gate/verify/{token}/check-in', [GateVerifyController::class, 'checkIn'])
        ->name('gate.verify.check-in');
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
| Gate Verification Page
|--------------------------------------------------------------------------
| This route displays the scanned invitee verification page.
| The actual check-in action is protected by auth above.
*/
Route::get('/gate/verify/{token}', [GateVerifyController::class, 'show'])
    ->name('gate.verify.show');