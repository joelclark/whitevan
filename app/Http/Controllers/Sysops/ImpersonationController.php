<?php

namespace App\Http\Controllers\Sysops;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function store(Request $request, Account $account): RedirectResponse
    {
        abort_if(! $request->user()?->isSysop(), 403);

        $request->session()->put('impersonated_account_id', $account->id);
        $request->session()->regenerate();

        ActivityLogger::event(
            ActivityEvent::SysopImpersonationStarted,
            metadata: ['account_id' => $account->id, 'account_name' => $account->name],
            account: $account,
            user: $request->user(),
        );

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->isSysop(), 403);

        $accountId = $request->session()->get('impersonated_account_id');

        if ($accountId === null) {
            return redirect()->route('sysops.accounts.index');
        }

        $account = Account::find($accountId);

        $request->session()->forget('impersonated_account_id');
        $request->session()->regenerate();

        ActivityLogger::event(
            ActivityEvent::SysopImpersonationStopped,
            metadata: ['account_id' => $accountId, 'account_name' => $account?->name],
            account: $account,
            user: $request->user(),
        );

        return redirect()->route('sysops.accounts.show', $accountId);
    }
}
