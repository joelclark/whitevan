<?php

use App\Http\Controllers\EstimateController;
use App\Http\Controllers\EstimateInterviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('estimates', [EstimateController::class, 'index'])->name('estimates.index');
    Route::post('customers/{customer}/estimates', [EstimateController::class, 'store'])
        ->name('customers.estimates.store');
    Route::get('estimates/{estimate}', [EstimateController::class, 'edit'])->name('estimates.edit');
    Route::patch('estimates/{estimate}', [EstimateController::class, 'update'])->name('estimates.update');
    Route::delete('estimates/{estimate}', [EstimateController::class, 'destroy'])->name('estimates.destroy');
    Route::post('estimates/{estimate}/retry', [EstimateController::class, 'retry'])->name('estimates.retry');
    Route::get('estimates/{estimate}/pdf', [EstimateController::class, 'pdf'])->name('estimates.pdf');
    Route::get('estimates/{estimate}/floorplan-pages/{page}', [EstimateController::class, 'floorplanPage'])
        ->where('page', '[0-9]+')
        ->name('estimates.floorplan-page');
    Route::post('estimates/{estimate}/interview/answer', [EstimateInterviewController::class, 'store'])
        ->name('estimates.interview.answer');
});
