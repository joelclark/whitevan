<?php

namespace App\Http\Middleware;

use App\Contexts\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes that operate on the sysop's own identity (profile,
 * password, 2FA) while an impersonation session is active.
 *
 * A real admin of the impersonated account has zero ability to edit the
 * sysop's identity; a sysop-in-impersonation shouldn't either, both for
 * mental-model consistency and to avoid confused audit rows where an
 * identity change would otherwise carry the impersonated account_id.
 */
class BlockDuringImpersonation
{
    public function __construct(private ImpersonationContext $impersonationContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonationContext->isImpersonating()) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Stop impersonating before changing your own account settings.');
        }

        return $next($request);
    }
}
