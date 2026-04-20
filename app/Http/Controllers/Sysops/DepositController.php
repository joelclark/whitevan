<?php

namespace App\Http\Controllers\Sysops;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sysops\DepositDefaultsUpdateRequest;
use App\Models\DepositDefaults;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function edit(): Response
    {
        $defaults = DepositDefaults::default();

        return Inertia::render('sysops/deposits/edit', [
            'defaults' => [
                'material_deposit_percent' => $defaults->material_deposit_percent,
                'labor_deposit_percent' => $defaults->labor_deposit_percent,
                'updated_at' => $defaults->updated_at,
            ],
        ]);
    }

    public function update(DepositDefaultsUpdateRequest $request): RedirectResponse
    {
        $defaults = DepositDefaults::default();
        $defaults->update($request->validated());

        ActivityLogger::event(
            ActivityEvent::DepositDefaultsUpdated,
            metadata: [
                'material_deposit_percent' => $defaults->material_deposit_percent,
                'labor_deposit_percent' => $defaults->labor_deposit_percent,
            ],
            user: $request->user(),
        );

        return back()->with('status', 'deposit-defaults-updated');
    }
}
