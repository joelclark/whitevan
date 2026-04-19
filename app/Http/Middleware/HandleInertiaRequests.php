<?php

namespace App\Http\Middleware;

use App\Contexts\AccountContext;
use App\Contexts\ImpersonationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private ImpersonationContext $impersonationContext) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $impersonating = $this->impersonationContext->isImpersonating();
        $impersonatedAccount = $this->impersonationContext->account();

        $auth = [
            'user' => $user,
            'account' => $impersonating ? $impersonatedAccount : app(AccountContext::class)->get(),
            'is_sysop' => $impersonating ? false : (bool) $user?->isSysop(),
            'security_groups' => $impersonating
                ? ['admin']
                : ($user
                    ?->loadMissing('securityGroupMemberships')
                    ->securityGroupsForAccount(app(AccountContext::class)->id())
                    ->map(fn ($group) => $group->value)
                    ->all() ?? []),
            'impersonating' => $impersonating && $impersonatedAccount
                ? ['account' => ['id' => $impersonatedAccount->id, 'name' => $impersonatedAccount->name]]
                : null,
        ];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => $auth,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'quote_changed' => fn () => $request->session()->get('quote_changed'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
