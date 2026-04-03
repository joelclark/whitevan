<?php

use App\Http\Controllers\Sysops\AccountController;
use App\Http\Controllers\Sysops\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'sysop'])->prefix('sysops')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('sysops.accounts.index');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('sysops.activity-logs.index');
    Route::get('/{account}', [AccountController::class, 'show'])->name('sysops.accounts.show');
});
