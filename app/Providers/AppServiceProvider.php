<?php

namespace App\Providers;

use App\Contexts\AccountContext;
use App\Contexts\ImpersonationContext;
use App\Enums\SecurityGroup;
use App\Http\Middleware\SetAccountContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Middleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccountContext::class);
        $this->app->singleton(ImpersonationContext::class);

        // Ensure SetAccountContext runs before HandleInertiaRequests so that
        // Inertia's share() can reflect the current account/impersonation state.
        // Inertia's own service provider hoists HandleInertiaRequests right
        // after StartSession in the middleware priority list, which would
        // otherwise run before SetAccountContext regardless of the order in
        // bootstrap/app.php.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureMiddlewarePriority();
    }

    /**
     * Ensure SetAccountContext runs before Inertia's middleware so the
     * Inertia share() callback reflects the current account/impersonation
     * state. Inertia's service provider hoists its middleware right after
     * StartSession in the priority list, which would otherwise run before
     * SetAccountContext regardless of the bootstrap/app.php order.
     */
    protected function configureMiddlewarePriority(): void
    {
        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(Middleware::class, SetAccountContext::class);
        }
    }

    /**
     * Configure authorization gates.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability) {
            $impersonating = app(ImpersonationContext::class)->isImpersonating();

            // Real sysops (not currently impersonating) bypass every check.
            if ($user->isSysop() && ! $impersonating) {
                return true;
            }

            // While impersonating, the sysop must be constrained to exactly
            // what a real Admin of the impersonated account could do — no
            // more. Consult Admin's ability list through the same matcher
            // used for real memberships so the two stay in lockstep if
            // Admin's abilities are ever narrowed or a non-wildcard group
            // is introduced.
            if ($impersonating) {
                if ($this->groupsAllow([SecurityGroup::Admin], $ability)) {
                    return true;
                }

                return null;
            }

            $user->loadMissing('securityGroupMemberships');

            $groups = $user->securityGroupMemberships
                ->pluck('security_group')
                ->all();

            if ($this->groupsAllow($groups, $ability)) {
                return true;
            }
        });
    }

    /**
     * Check whether any of the given security groups grant the ability.
     *
     * @param  array<int, SecurityGroup>  $groups
     */
    protected function groupsAllow(array $groups, string $ability): bool
    {
        foreach ($groups as $group) {
            $abilities = $group->abilities();

            if (in_array('*', $abilities, true) || in_array($ability, $abilities, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
