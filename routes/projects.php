<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('customers/{customer}/projects', [ProjectController::class, 'store'])
        ->name('customers.projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
});
