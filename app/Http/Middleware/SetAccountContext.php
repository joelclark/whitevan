<?php

namespace App\Http\Middleware;

use App\Contexts\AccountContext;
use App\Contexts\ImpersonationContext;
use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAccountContext
{
    public function __construct(
        private AccountContext $accountContext,
        private ImpersonationContext $impersonationContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $impersonatedId = $request->session()->get('impersonated_account_id');

        if ($user?->isSysop() && $impersonatedId !== null) {
            $account = Account::find($impersonatedId);

            if ($account) {
                $this->accountContext->set($account);
                $this->impersonationContext->start($account);

                return $next($request);
            }

            $request->session()->forget('impersonated_account_id');
        }

        if ($user) {
            $this->accountContext->resolveForUser($user);
        }

        return $next($request);
    }
}
