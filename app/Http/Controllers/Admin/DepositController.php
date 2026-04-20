<?php

namespace App\Http\Controllers\Admin;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountDepositOverrideUpdateRequest;
use App\Models\AccountDepositOverride;
use App\Services\ActivityLogger;
use App\Services\DepositResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function edit(
        AccountContext $accountContext,
        DepositResolver $resolver,
    ): Response {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $override = $resolver->overrideFor($account);
        $defaults = $resolver->defaults();

        return Inertia::render('admin/deposits/edit', [
            'override' => $override !== null
                ? [
                    'material_deposit_percent' => $override->material_deposit_percent,
                    'labor_deposit_percent' => $override->labor_deposit_percent,
                    'updated_at' => $override->updated_at,
                ]
                : null,
            'defaults' => [
                'material_deposit_percent' => $defaults->material_deposit_percent,
                'labor_deposit_percent' => $defaults->labor_deposit_percent,
            ],
            'resolved' => [
                'material_deposit_percent' => $resolver->materialPercentFor($account),
                'labor_deposit_percent' => $resolver->laborPercentFor($account),
            ],
        ]);
    }

    public function update(
        AccountDepositOverrideUpdateRequest $request,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $validated = $request->validated();

        AccountDepositOverride::withoutGlobalScope('account')->updateOrCreate(
            ['account_id' => $account->id],
            [
                'material_deposit_percent' => $validated['material_deposit_percent'] ?? null,
                'labor_deposit_percent' => $validated['labor_deposit_percent'] ?? null,
            ],
        );

        ActivityLogger::event(
            ActivityEvent::AccountDepositOverrideUpdated,
            metadata: [
                'material_deposit_percent' => $validated['material_deposit_percent'] ?? null,
                'labor_deposit_percent' => $validated['labor_deposit_percent'] ?? null,
            ],
            account: $account,
            user: $request->user(),
        );

        return back()->with('status', 'deposits-updated');
    }

    public function destroy(
        Request $request,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        AccountDepositOverride::withoutGlobalScope('account')
            ->where('account_id', $account->id)
            ->delete();

        ActivityLogger::event(
            ActivityEvent::AccountDepositOverrideReverted,
            account: $account,
            user: $request->user(),
        );

        return back()->with('status', 'deposits-reverted');
    }
}
