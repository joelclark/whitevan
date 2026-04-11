<?php

namespace App\Http\Middleware;

use App\Contexts\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSysop
{
    public function __construct(private ImpersonationContext $impersonationContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSysop()) {
            abort(403);
        }

        // Impersonation must be stopped before re-entering sysop routes. The
        // stop endpoint deliberately lives outside this middleware group.
        if ($this->impersonationContext->isImpersonating()) {
            abort(403);
        }

        return $next($request);
    }
}
