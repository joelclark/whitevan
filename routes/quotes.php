<?php

use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

// Public — no auth required. Access is gated by the unguessable quote token.
Route::get('quotes/{token}', [QuoteController::class, 'show'])
    ->name('quotes.show');

Route::get('quotes/{token}/floorplan-pages/{page}', [QuoteController::class, 'floorplanPage'])
    ->where('page', '[0-9]+')
    ->name('quotes.floorplan-page');

// Authenticated — converts an estimate into a customer-visible quote.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('estimates/{estimate}/send-quote', [QuoteController::class, 'send'])
        ->name('estimates.send-quote');
});
