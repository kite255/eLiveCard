<?php

use App\Http\Controllers\GateVerifyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/gate/verify/{token}', [GateVerifyController::class, 'show'])
    ->name('gate.verify.show');

Route::post('/gate/verify/{token}/check-in', [GateVerifyController::class, 'checkIn'])
    ->middleware('auth')
    ->name('gate.verify.check-in');