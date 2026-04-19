<?php

namespace App\Http\Controllers\Admin;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountContractUpdateRequest;
use App\Models\AccountContractOverride;
use App\Services\ActivityLogger;
use App\Services\ContractMarkdown;
use App\Services\ContractResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function edit(
        AccountContext $accountContext,
        ContractResolver $resolver,
        ContractMarkdown $markdown,
    ): Response {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        $override = $resolver->overrideFor($account);
        $default = $resolver->default();

        return Inertia::render('admin/contract/edit', [
            'override' => $override !== null
                ? ['body' => $override->body, 'updated_at' => $override->updated_at]
                : null,
            'default' => [
                'body' => $default->body,
                'preview_html' => $markdown->toHtml($default->body),
            ],
        ]);
    }

    public function update(
        AccountContractUpdateRequest $request,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        AccountContractOverride::withoutGlobalScope('account')->updateOrCreate(
            ['account_id' => $account->id],
            ['body' => $request->validated('body')],
        );

        ActivityLogger::event(
            ActivityEvent::AccountContractUpdated,
            account: $account,
            user: $request->user(),
        );

        return back()->with('status', 'contract-updated');
    }

    public function destroy(
        Request $request,
        AccountContext $accountContext,
    ): RedirectResponse {
        $account = $accountContext->get();
        abort_if($account === null, 403);

        AccountContractOverride::withoutGlobalScope('account')
            ->where('account_id', $account->id)
            ->delete();

        ActivityLogger::event(
            ActivityEvent::AccountContractReverted,
            account: $account,
            user: $request->user(),
        );

        return back()->with('status', 'contract-reverted');
    }
}
