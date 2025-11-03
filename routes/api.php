<?php

use App\Http\Controllers\Api\TrackingController;
use App\Http\Middleware\AuthenticateIngestionToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('tracking')->name('tracking.')->group(function () {
    // Health check endpoint (no auth required)
    Route::get('health', [TrackingController::class, 'health'])->name('health');

    // Tracking event ingestion endpoint (protected by ingestion token)
    Route::post('events', [TrackingController::class, 'track'])
        ->middleware(AuthenticateIngestionToken::class)
        ->name('events');
});
