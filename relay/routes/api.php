<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\PairingController;
use Illuminate\Support\Facades\Route;

Route::post('/access-requests', [AccessRequestController::class, 'store'])
    ->middleware('throttle:access-requests');

Route::post('/pair', [PairingController::class, 'store'])
    ->middleware('throttle:pair');
