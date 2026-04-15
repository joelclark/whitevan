<?php

use App\Contexts\ImpersonationContext;
use App\Http\Controllers\Sysops\ImpersonationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        if (auth()->user()?->isSysop() && ! app(ImpersonationContext::class)->isImpersonating()) {
            return redirect()->route('sysops.dashboard');
        }

        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.stop');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/sysops.php';
require __DIR__.'/customers.php';
require __DIR__.'/estimates.php';
