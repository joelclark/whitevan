<?php

use App\Http\Controllers\Sysops\AccountController;
use App\Http\Controllers\Sysops\ActivityLogController;
use App\Http\Controllers\Sysops\AiAgentController;
use App\Http\Controllers\Sysops\ContractTemplateController;
use App\Http\Controllers\Sysops\DashboardController;
use App\Http\Controllers\Sysops\ImpersonationController;
use App\Http\Controllers\Sysops\SecurityGroupController;
use App\Http\Controllers\Sysops\UserActivationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'sysop'])->prefix('sysops')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('sysops.accounts.index');
    Route::get('/dashboard', DashboardController::class)->name('sysops.dashboard');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('sysops.activity-logs.index');
    Route::get('/ai-agents', [AiAgentController::class, 'index'])->name('sysops.ai-agents.index');
    Route::get('/ai-agents/{aiAgentSetting}', [AiAgentController::class, 'edit'])->name('sysops.ai-agents.edit');
    Route::put('/ai-agents/{aiAgentSetting}', [AiAgentController::class, 'update'])->name('sysops.ai-agents.update');
    Route::get('/contract-templates', [ContractTemplateController::class, 'index'])->name('sysops.contract-templates.index');
    Route::get('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'edit'])->name('sysops.contract-templates.edit');
    Route::put('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'update'])->name('sysops.contract-templates.update');
    Route::get('/{account}', [AccountController::class, 'show'])->name('sysops.accounts.show');
    Route::post('/{account}/impersonate', [ImpersonationController::class, 'store'])->name('sysops.accounts.impersonate');
    Route::put('/{account}/users/{user}/security-groups', [SecurityGroupController::class, 'update'])->name('sysops.accounts.users.security-groups.update');
    Route::put('/{account}/users/{user}/activation', [UserActivationController::class, 'update'])->name('sysops.accounts.users.activation.update');
});
