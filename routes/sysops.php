<?php

use App\Http\Controllers\Sysops\AccountController;
use App\Http\Controllers\Sysops\ActivityLogController;
use App\Http\Controllers\Sysops\SecurityGroupController;
use App\Http\Controllers\Sysops\UserActivationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'sysop'])->prefix('sysops')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('sysops.accounts.index');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('sysops.activity-logs.index');
    Route::get('/{account}', [AccountController::class, 'show'])->name('sysops.accounts.show');
    Route::put('/{account}/users/{user}/security-groups', [SecurityGroupController::class, 'update'])->name('sysops.accounts.users.security-groups.update');
    Route::put('/{account}/users/{user}/activation', [UserActivationController::class, 'update'])->name('sysops.accounts.users.activation.update');
});
