<?php

use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\SecurityGroupController;
use App\Http\Controllers\Admin\UserActivationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])->prefix('admin')->group(function () {
    Route::redirect('/', '/admin/users');
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::put('/users/{user}/security-groups', [SecurityGroupController::class, 'update'])->name('admin.users.security-groups.update');
    Route::put('/users/{user}/activation', [UserActivationController::class, 'update'])->name('admin.users.activation.update');
    Route::get('/contract', [ContractController::class, 'edit'])->name('admin.contract.edit');
    Route::put('/contract', [ContractController::class, 'update'])->name('admin.contract.update');
    Route::delete('/contract', [ContractController::class, 'destroy'])->name('admin.contract.destroy');
    Route::get('/deposits', [DepositController::class, 'edit'])->name('admin.deposits.edit');
    Route::put('/deposits', [DepositController::class, 'update'])->name('admin.deposits.update');
    Route::delete('/deposits', [DepositController::class, 'destroy'])->name('admin.deposits.destroy');
});
