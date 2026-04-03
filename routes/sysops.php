<?php

use App\Http\Controllers\Sysops\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'sysop'])->prefix('sysops')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('sysops.accounts.index');
    Route::get('/{account}', [AccountController::class, 'show'])->name('sysops.accounts.show');
});
