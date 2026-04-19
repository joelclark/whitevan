<?php

use App\Http\Controllers\ApprovalFlowController;
use App\Http\Controllers\ContractSigningController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

// Public — no auth required. Access is gated by the unguessable approval_token
// (a 128-bit ULID). No signature is applied: email providers and click-tracking
// layers routinely append query parameters, which would invalidate an HMAC and
// bounce legitimate customers with a 403. The token alone is the access control.
Route::get('approve/{approval_token}', [ApprovalFlowController::class, 'show'])
    ->name('approve.show');
Route::get('approve/{approval_token}/sign', [ContractSigningController::class, 'show'])
    ->name('approve.sign.show');
Route::post('approve/{approval_token}/sign', [ContractSigningController::class, 'store'])
    ->name('approve.sign');

// Authenticated — converts an estimate into a customer-visible quote.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('estimates/{estimate}/send-quote', [QuoteController::class, 'send'])
        ->name('estimates.send-quote');
});
