<?php

namespace App\Http\Middleware;

use App\Contexts\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAccountContext
{
    public function __construct(private AccountContext $accountContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->account) {
            $this->accountContext->set($user->account);
        }

        return $next($request);
    }
}
