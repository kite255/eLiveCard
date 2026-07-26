<?php

use App\Exports\InviteeSampleExport;
use App\Http\Controllers\CardTemplateDesignerController;
use App\Http\Controllers\GateCheckInController;
use App\Http\Controllers\GateVerifyController;
use App\Http\Controllers\InviteeLocationController;
use App\Http\Controllers\InviteePageController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicRsvpReportController;
use App\Http\Controllers\RsvpController;
use App\Http\Controllers\RsvpShareController;
use App\Models\CardTemplate;
use App\Models\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/*
|--------------------------------------------------------------------------
| Public Website
|--------------------------------------------------------------------------
|
| Public marketing and information pages for eLive Card.
|
*/
Route::view('/', 'welcome')
    ->name('home');

Route::get('/events', [PublicEventController::class, 'index'])
    ->name('events.index');

Route::get('/events/{event}', [PublicEventController::class, 'show'])
    ->whereNumber('event')
    ->name('events.show');

Route::view('/about', 'pages.about')
    ->name('about');

Route::view('/contact', 'pages.contact')
    ->name('contact');

Route::view('/privacy-policy', 'pages.privacy-policy')
    ->name('privacy-policy');

Route::view('/terms', 'pages.terms')
    ->name('terms');

/*
|--------------------------------------------------------------------------
| Public Invitee Digital Page
|--------------------------------------------------------------------------
|
| Main invitee-facing page opened from SMS or WhatsApp.
|
| Example:
| Local: http://127.0.0.1:8002/i/NPTUIN
| Live:  https://digital.elive.co.tz/i/NPTUIN
|
*/
Route::get('/i/{shortCode}', [InviteePageController::class, 'show'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.page');

Route::post('/i/{shortCode}/rsvp', [RsvpController::class, 'submit'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.rsvp');

Route::post('/i/{shortCode}/wish', [InviteePageController::class, 'storeWish'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.wish');

Route::put(
    '/i/{shortCode}/wishes/{wish}',
    [InviteePageController::class, 'updateWish']
)
    ->where('shortCode', '[A-Za-z0-9]+')
    ->whereNumber('wish')
    ->name('invitee.wish.update');

Route::post('/i/{shortCode}/photo', [InviteePageController::class, 'storePhoto'])
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.photo');

Route::put(
    '/i/{shortCode}/photos/{photo}',
    [InviteePageController::class, 'updatePhoto']
)
    ->where('shortCode', '[A-Za-z0-9]+')
    ->whereNumber('photo')
    ->name('invitee.photo.update');

Route::delete(
    '/i/{shortCode}/photos/{photo}',
    [InviteePageController::class, 'deletePhoto']
)
    ->where('shortCode', '[A-Za-z0-9]+')
    ->whereNumber('photo')
    ->name('invitee.photo.delete');

/*
|--------------------------------------------------------------------------
| Public Invitee Location Redirect
|--------------------------------------------------------------------------
|
| Used by the WhatsApp LOCATION button.
|
| Example:
| Local: http://127.0.0.1:8002/l/DI9YD5
| Live:  https://digital.elive.co.tz/l/DI9YD5
|
| The route redirects to the latest Google Maps link saved in the admin
| event dashboard. This allows you to update the location later without
| changing old WhatsApp messages.
|
*/
Route::get('/l/{shortCode}', InviteeLocationController::class)
    ->where('shortCode', '[A-Za-z0-9]+')
    ->name('invitee.location');

/*
|--------------------------------------------------------------------------
| Standalone RSVP Confirmation Page
|--------------------------------------------------------------------------
|
| Useful when invitee opens direct RSVP link.
|
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
| Public Read-only RSVP Client Report
|--------------------------------------------------------------------------
|
| Secure client-facing RSVP progress page.
| The token is stored as a SHA-256 hash on the event record.
|
*/
Route::get(
    '/rsvp-report/{token}',
    [PublicRsvpReportController::class, 'show']
)
    ->where('token', '[A-Za-z0-9]{40,128}')
    ->middleware('throttle:120,1')
    ->name('public.rsvp-report');

Route::get(
    '/r/{share}',
    [PublicRsvpReportController::class, 'showShort']
)
    ->where('share', '[a-z0-9-]+-[A-Za-z0-9]{10}')
    ->middleware('throttle:120,1')
    ->name('public.rsvp-report.short');

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
| Serves uploaded template images through Laravel.
| Useful when direct /storage access returns 403 Forbidden.
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
| Displays scanned invitee verification result.
| The QR token itself is secure. Actual check-in submit is protected by auth.
|
*/
Route::get('/gate/verify/{token}', [GateVerifyController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:120,1')
    ->name('gate.verify.show');

/*
|--------------------------------------------------------------------------
| Authenticated Admin/User Routes
|--------------------------------------------------------------------------
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
    | RSVP Client Sharing Controls
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized event managers to:
    | - view the current secure client report link,
    | - generate or regenerate the link,
    | - disable the link.
    |
    */
    Route::get(
        '/admin/events/{event}/rsvp-share',
        [RsvpShareController::class, 'show']
    )
        ->whereNumber('event')
        ->name('admin.events.rsvp-share.show');

    Route::post(
        '/admin/events/{event}/rsvp-share',
        [RsvpShareController::class, 'generate']
    )
        ->whereNumber('event')
        ->middleware('throttle:30,1')
        ->name('admin.events.rsvp-share.generate');

    Route::delete(
        '/admin/events/{event}/rsvp-share',
        [RsvpShareController::class, 'disable']
    )
        ->whereNumber('event')
        ->middleware('throttle:30,1')
        ->name('admin.events.rsvp-share.disable');

    /*
    |--------------------------------------------------------------------------
    | Gate Scanner Entry Route
    |--------------------------------------------------------------------------
    |
    | Opens the selected event scanner when an event ID is supplied.
    | Without an event ID, redirects to the events page so the user can
    | choose the correct event before scanning.
    |
    */
    Route::get('/admin/gate-check-in', function () {
        $user = auth()->user();
        $eventId = request()->integer('event');

        if ($eventId <= 0) {
            return redirect('/admin/dashboard')
                ->with('warning', 'Select an event before opening the gate scanner.');
        }

        $event = Event::query()->find($eventId);

        if (! $event) {
            return redirect('/admin/dashboard')
                ->with('warning', 'The selected event could not be found.');
        }

        $canAccessEvent = $user
            && (
                (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
                || (int) $event->user_id === (int) $user->id
                || (
                    method_exists($event, 'canBeCheckedInBy')
                    && $event->canBeCheckedInBy($user)
                )
                || (
                    method_exists($user, 'canAccessEvent')
                    && $user->canAccessEvent($event)
                )
            );

        abort_unless($canAccessEvent, 403);

        return redirect()->route('gate.check-in.show', [
            'event' => $event->getKey(),
        ]);
    })->name('gate.check-in.entry');

    /*
    |--------------------------------------------------------------------------
    | Professional Gate Check-in Page
    |--------------------------------------------------------------------------
    |
    | Scanner page:
    | GET /gate/events/{event}/check-in
    |
    | QR/manual verification:
    | POST /gate/events/{event}/verify
    |
    | Manual search alias:
    | POST /gate/events/{event}/manual-search
    |
    | Both verify and manual-search can accept:
    | scanned_value, search, serial number, phone, name, or short code,
    | depending on GateCheckInController::verify().
    |
    */
    Route::get(
        '/gate/events/{event}/check-in',
        [GateCheckInController::class, 'show']
    )
        ->whereNumber('event')
        ->name('gate.check-in.show');

    Route::post(
        '/gate/events/{event}/verify',
        [GateCheckInController::class, 'verify']
    )
        ->whereNumber('event')
        ->middleware('throttle:120,1')
        ->name('gate.check-in.verify');

    /*
    |--------------------------------------------------------------------------
    | Manual Search Route Alias
    |--------------------------------------------------------------------------
    |
    | This fixes manual search forms that submit to gate.manual-search.
    | It uses the same verify() method so you do not need a separate
    | manualSearch() method unless you want one later.
    |
    */
    Route::post(
        '/gate/events/{event}/manual-search',
        [GateCheckInController::class, 'verify']
    )
        ->whereNumber('event')
        ->middleware('throttle:120,1')
        ->name('gate.manual-search');

    Route::post(
        '/gate/events/{event}/confirm',
        [GateCheckInController::class, 'confirm']
    )
        ->whereNumber('event')
        ->middleware('throttle:120,1')
        ->name('gate.check-in.confirm');

    /*
    |--------------------------------------------------------------------------
    | QR Token Check-in Submit
    |--------------------------------------------------------------------------
    */
    Route::post(
        '/gate/verify/{token}/check-in',
        [GateVerifyController::class, 'checkIn']
    )
        ->where('token', '[A-Za-z0-9]+')
        ->middleware('throttle:120,1')
        ->name('gate.verify.check-in');
});
